<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Enums\PermissionEffect;
use ImranDevBD\RoleMatrix\Events\PermissionsGrantedToRole;
use ImranDevBD\RoleMatrix\Events\PermissionsRevokedFromRole;
use ImranDevBD\RoleMatrix\Events\RoleAssignedToUser;
use ImranDevBD\RoleMatrix\Events\RoleRemovedFromUser;
use ImranDevBD\RoleMatrix\Exceptions\RoleMatrixException;
use ImranDevBD\RoleMatrix\Models\ModelPermission;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Models\RolePermission;
use ImranDevBD\RoleMatrix\Support\AuditContext;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\SubjectResolver;
use ImranDevBD\RoleMatrix\Support\TeamContext;

final class AssignmentService
{
    public function __construct(
        private readonly SubjectResolver $subjects,
        private readonly SuperAdminGuard $guard,
        private readonly CacheService $cache,
        private readonly AuditService $audit,
        private readonly AuditContext $auditContext,
        private readonly TeamContext $teams,
    ) {}

    /**
     * @param  array<mixed>  $roles
     */
    public function assign(Model $user, array $roles, ?DateTimeInterface $expiresAt = null, ?string $guard = null): void
    {
        if ($expiresAt !== null && ! RoleMatrixConfig::temporaryRolesEnabled()) {
            throw new RoleMatrixException('Temporary roles are disabled.');
        }

        $guard ??= $this->guardFor($user);
        $scope = $this->scope();

        foreach ($this->subjects->roles($roles, $guard) as $role) {
            ModelRole::query()->updateOrCreate(
                [
                    'role_id' => $role->getKey(),
                    'model_type' => $user->getMorphClass(),
                    'model_id' => (string) $user->getKey(),
                    'scope_key' => $scope,
                ],
                [
                    'team_id' => $scope === '' ? null : $scope,
                    'expires_at' => $expiresAt,
                    'assigned_by' => $this->actorId(),
                ],
            );

            $this->audit->record(
                AuditEvent::RoleAssignedToUser,
                $user->getMorphClass(),
                $user->getKey(),
                null,
                ['role' => $role->slug, 'expires_at' => $expiresAt?->format(DATE_ATOM)],
            );
            event(new RoleAssignedToUser($role, $user));
        }

        $this->cache->forgetUser($user);
    }

    /**
     * @param  array<mixed>  $roles
     */
    public function remove(Model $user, array $roles, ?string $guard = null): void
    {
        $guard ??= $this->guardFor($user);

        foreach ($this->subjects->roles($roles, $guard) as $role) {
            $this->guard->assertCanDetach($role, $user);
            ModelRole::query()
                ->where('role_id', $role->getKey())
                ->where('model_type', $user->getMorphClass())
                ->where('model_id', (string) $user->getKey())
                ->when(RoleMatrixConfig::teamsEnabled(), fn ($query) => $query->where('scope_key', $this->scope()))
                ->delete();

            $this->audit->record(AuditEvent::RoleRemovedFromUser, $user->getMorphClass(), $user->getKey(), ['role' => $role->slug], null);
            event(new RoleRemovedFromUser($role, $user));
        }

        $this->cache->forgetUser($user);
    }

    /**
     * @param  array<mixed>  $roles
     */
    public function sync(Model $user, array $roles, ?string $guard = null): void
    {
        $guard ??= $this->guardFor($user);
        $resolved = $this->subjects->roles($roles, $guard);
        $ids = array_map(static fn (Role $role): string => (string) $role->getKey(), $resolved);
        $scope = $this->scope();

        $current = ModelRole::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getKey())
            ->when(RoleMatrixConfig::teamsEnabled(), fn ($query) => $query->where('scope_key', $scope))
            ->get();

        foreach ($current as $assignment) {
            if (in_array((string) $assignment->role_id, $ids, true)) {
                continue;
            }

            $role = Role::query()->find($assignment->role_id);

            if ($role instanceof Role) {
                $this->guard->assertCanDetach($role, $user);
                event(new RoleRemovedFromUser($role, $user));
            }

            $assignment->delete();
        }

        if ($resolved !== []) {
            $this->assign($user, $resolved, null, $guard);
        } else {
            $this->cache->forgetUser($user);
        }
    }

    /**
     * @param  array<mixed>  $permissions
     * @param  array<string, mixed>|null  $conditions
     */
    public function give(Model $user, array $permissions, ?array $conditions = null, string $effect = 'allow'): void
    {
        if (! RoleMatrixConfig::directPermissionsEnabled()) {
            throw new RoleMatrixException('Direct permissions are disabled.');
        }

        $guard = $this->guardFor($user);
        $scope = $this->scope();

        foreach ($this->subjects->permissions($permissions, $guard, true) as $permission) {
            ModelPermission::query()->updateOrCreate(
                [
                    'permission_id' => $permission->getKey(),
                    'model_type' => $user->getMorphClass(),
                    'model_id' => (string) $user->getKey(),
                    'scope_key' => $scope,
                    'effect' => $effect,
                ],
                [
                    'team_id' => $scope === '' ? null : $scope,
                    'conditions' => $conditions,
                    'assigned_by' => $this->actorId(),
                    'expires_at' => null,
                ],
            );
        }

        $this->cache->forgetUser($user);
        $this->audit->record(AuditEvent::DirectPermissionChanged, $user->getMorphClass(), $user->getKey(), null, [
            'effect' => $effect,
            'permissions' => array_map(static fn (Permission $permission): string => $permission->name, $this->subjects->permissions($permissions, $guard, false)),
        ]);
    }

    /**
     * @param  array<mixed>  $permissions
     */
    public function revoke(Model $user, array $permissions): void
    {
        $guard = $this->guardFor($user);
        $ids = array_map(
            static fn (Permission $permission): mixed => $permission->getKey(),
            $this->subjects->permissions($permissions, $guard, false),
        );

        ModelPermission::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getKey())
            ->whereIn('permission_id', $ids)
            ->delete();

        $this->cache->forgetUser($user);
    }

    /**
     * @param  array<mixed>  $permissions
     */
    public function syncDirect(Model $user, array $permissions): void
    {
        $guard = $this->guardFor($user);
        $resolved = $this->subjects->permissions($permissions, $guard, true);
        $ids = array_map(static fn (Permission $permission): mixed => $permission->getKey(), $resolved);

        ModelPermission::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getKey())
            ->where('effect', PermissionEffect::Allow->value)
            ->when(RoleMatrixConfig::teamsEnabled(), fn ($query) => $query->where('scope_key', $this->scope()))
            ->when($ids !== [], fn ($query) => $query->whereNotIn('permission_id', $ids), fn ($query) => $query)
            ->delete();

        if ($resolved !== []) {
            $this->give($user, $resolved);
        } else {
            $this->cache->forgetUser($user);
        }
    }

    /**
     * @param  list<string>  $grant
     * @param  list<string>  $revoke
     * @param  array<string, array<string, mixed>|null>  $conditions
     */
    public function applyRoleDiff(Role $role, array $grant, array $revoke, array $conditions = []): void
    {
        $this->guard->assertCanChangeGrants($role, $revoke, $conditions);

        DB::transaction(function () use ($role, $grant, $revoke, $conditions): void {
            $batch = $this->auditContext->newBatch();
            $this->auditContext->pause();

            try {
                if ($revoke !== []) {
                    $ids = Permission::query()->forGuard($role->guard_name)->whereIn('name', $revoke)->pluck('id');
                    RolePermission::query()->where('role_id', $role->getKey())->whereIn('permission_id', $ids)->delete();
                }

                foreach ($grant as $name) {
                    $permission = $this->subjects->permission($name, $role->guard_name, false);
                    RolePermission::query()->updateOrCreate(
                        ['role_id' => $role->getKey(), 'permission_id' => $permission->getKey()],
                        [
                            'conditions' => $conditions[$name] ?? null,
                            'granted_at' => now(),
                            'granted_by' => $this->actorId(),
                        ],
                    );
                }

                foreach ($conditions as $name => $payload) {
                    if (in_array($name, $grant, true)) {
                        continue;
                    }

                    $permission = Permission::query()->forGuard($role->guard_name)->where('name', $name)->first();

                    if (! $permission instanceof Permission) {
                        continue;
                    }

                    RolePermission::query()
                        ->where('role_id', $role->getKey())
                        ->where('permission_id', $permission->getKey())
                        ->update([
                            'conditions' => $payload === null ? null : json_encode($payload),
                            'updated_at' => now(),
                        ]);
                }
            } finally {
                $this->auditContext->resume();
            }

            if ($grant !== []) {
                $this->audit->record(AuditEvent::PermissionsGrantedToRole, $role->getMorphClass(), $role->getKey(), null, ['grant' => $grant], $batch);
                event(new PermissionsGrantedToRole($role, $grant));
            }

            if ($revoke !== []) {
                $this->audit->record(AuditEvent::PermissionsRevokedFromRole, $role->getMorphClass(), $role->getKey(), ['revoke' => $revoke], null, $batch);
                event(new PermissionsRevokedFromRole($role, $revoke));
            }

            if ($conditions !== []) {
                $this->audit->record(AuditEvent::PermissionsGrantedToRole, $role->getMorphClass(), $role->getKey(), null, ['conditions' => array_keys($conditions)], $batch);
            }
        });

        $this->cache->forgetAll();
    }

    public function assignDefaults(Model $user): void
    {
        $guard = $this->guardFor($user);
        $already = ModelRole::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getKey())
            ->exists();

        if ($already) {
            return;
        }

        $roles = Role::query()->forGuard($guard)->active()->where('is_default', true)->get()->all();

        if ($roles === []) {
            return;
        }

        $this->assign($user, $roles, null, $guard);
    }

    private function scope(): string
    {
        return RoleMatrixConfig::teamsEnabled() ? $this->teams->scopeKey() : '';
    }

    private function guardFor(Model $user): string
    {
        if (method_exists($user, 'roleMatrixGuard')) {
            return (string) $user->roleMatrixGuard();
        }

        $guard = $user->getAttribute('guard_name');

        return is_string($guard) && $guard !== '' ? $guard : RoleMatrixConfig::defaultGuard();
    }

    private function actorId(): ?string
    {
        $actor = auth()->user();

        return $actor instanceof Model ? (string) $actor->getKey() : null;
    }
}
