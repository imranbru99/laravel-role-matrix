<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ImranDevBD\RoleMatrix\Contracts\RoleServiceContract;
use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Enums\RoleStatus;
use ImranDevBD\RoleMatrix\Events\RoleCreated;
use ImranDevBD\RoleMatrix\Events\RoleDeleted;
use ImranDevBD\RoleMatrix\Events\RoleRestored;
use ImranDevBD\RoleMatrix\Events\RoleUpdated;
use ImranDevBD\RoleMatrix\Exceptions\CircularHierarchyException;
use ImranDevBD\RoleMatrix\Exceptions\RoleInUseException;
use ImranDevBD\RoleMatrix\Exceptions\RoleMatrixException;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Support\AuditContext;
use ImranDevBD\RoleMatrix\Support\DefaultRoles;
use ImranDevBD\RoleMatrix\Support\HierarchyWalker;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class RoleService implements RoleServiceContract
{
    public function __construct(
        private readonly SuperAdminGuard $guard,
        private readonly AuditService $audit,
        private readonly AuditContext $auditContext,
        private readonly CacheService $cache,
    ) {}

    public function create(array $attributes): Role
    {
        $attributes['guard_name'] ??= RoleMatrixConfig::defaultGuard();
        $attributes['slug'] = Str::slug((string) ($attributes['slug'] ?? $attributes['name'] ?? ''));
        $attributes['status'] ??= RoleStatus::Active->value;
        $attributes['level'] ??= 0;

        if ($attributes['slug'] === '') {
            throw new RoleMatrixException('A role needs a name.');
        }

        $this->assertHierarchy($attributes, null);

        $role = DB::transaction(function () use ($attributes): Role {
            $role = Role::query()->create($attributes);
            $this->audit->record(AuditEvent::RoleCreated, $role->getMorphClass(), $role->getKey(), null, $role->withoutRelations()->toArray());

            return $role;
        });

        DB::afterCommit(fn () => event(new RoleCreated($role)));
        $this->cache->forgetAll();

        return $role;
    }

    public function update(Role $role, array $attributes): Role
    {
        $this->guard->assertCanUpdate($role, $attributes);

        if (isset($attributes['name']) && ! isset($attributes['slug']) && ! $role->is_super_admin) {
            $attributes['slug'] = Str::slug((string) $attributes['name']);
        }

        if (array_key_exists('parent_id', $attributes) || isset($attributes['guard_name'])) {
            $this->assertHierarchy($attributes + ['guard_name' => $role->guard_name], $role);
        }

        $before = $role->withoutRelations()->toArray();

        $role = DB::transaction(function () use ($role, $attributes, $before): Role {
            $role->fill($attributes);
            $role->save();
            $this->audit->record(AuditEvent::RoleUpdated, $role->getMorphClass(), $role->getKey(), $before, $role->withoutRelations()->toArray());

            return $role;
        });

        DB::afterCommit(fn () => event(new RoleUpdated($role, $attributes)));
        $this->cache->forgetAll();

        return $role->refresh();
    }

    public function delete(Role $role, ?Role $reassignTo = null, bool $detach = false): void
    {
        $this->guard->assertCanDelete($role);
        $count = $role->activeAssignmentCount();

        if ($count > 0 && $reassignTo === null && ! $detach) {
            throw new RoleInUseException("Role [{$role->slug}] is assigned to {$count} users. Reassign them or detach them explicitly.");
        }

        if ($reassignTo !== null && (string) $reassignTo->getKey() === (string) $role->getKey()) {
            throw new RoleMatrixException('Choose a different role to receive the users.');
        }

        DB::transaction(function () use ($role, $reassignTo, $detach): void {
            if ($reassignTo instanceof Role) {
                $this->reassign($role, $reassignTo);
            } elseif ($detach) {
                ModelRole::query()->where('role_id', $role->getKey())->delete();
            }

            $this->auditContext->withoutObservers(fn () => $role->delete());
            $this->audit->record(AuditEvent::RoleDeleted, $role->getMorphClass(), $role->getKey(), $role->toArray(), null);
        });

        DB::afterCommit(fn () => event(new RoleDeleted($role)));
        $this->cache->forgetAll();
    }

    public function restore(Role $role): Role
    {
        $role->restore();
        $this->audit->record(AuditEvent::RoleRestored, $role->getMorphClass(), $role->getKey(), null, ['slug' => $role->slug]);
        DB::afterCommit(fn () => event(new RoleRestored($role)));
        $this->cache->forgetAll();

        return $role->refresh();
    }

    public function forceDelete(Role $role): void
    {
        $this->guard->assertCanDelete($role);
        $snapshot = $role->toArray();
        $this->auditContext->withoutObservers(fn () => $role->forceDelete());
        $this->audit->record(AuditEvent::RoleForceDeleted, $role->getMorphClass(), $snapshot['id'] ?? null, $snapshot, null);
        $this->cache->forgetAll();
    }

    public function seedDefaults(string $guard): void
    {
        /** @var array<string, Role> $created */
        $created = [];

        foreach (DefaultRoles::definitions() as $definition) {
            $parent = $definition['parent'];
            unset($definition['parent']);
            $role = Role::query()->firstOrCreate(
                ['slug' => $definition['slug'], 'guard_name' => $guard],
                $definition + ['guard_name' => $guard, 'status' => RoleStatus::Active->value],
            );
            $created[$definition['slug']] = $role;
            $definition['parent'] = $parent;
        }

        foreach (DefaultRoles::definitions() as $definition) {
            if ($definition['parent'] === null) {
                continue;
            }

            $role = $created[$definition['slug']];
            $parent = $created[$definition['parent']] ?? null;

            if ($parent instanceof Role && $role->parent_id === null && ! $role->is_super_admin) {
                $role->parent_id = $parent->getKey();
                $role->save();
            }
        }

        $this->cache->forgetAll();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertHierarchy(array $attributes, ?Role $role): void
    {
        if (! array_key_exists('parent_id', $attributes) || $attributes['parent_id'] === null || $attributes['parent_id'] === '') {
            return;
        }

        $guard = $attributes['guard_name'] ?? null;
        $guard = is_string($guard) && $guard !== ''
            ? $guard
            : ($role instanceof Role ? $role->guard_name : RoleMatrixConfig::defaultGuard());
        $parent = Role::query()->forGuard($guard)->find($attributes['parent_id']);

        if (! $parent instanceof Role) {
            throw new RoleMatrixException('The parent role does not exist on this guard.');
        }

        $becomesSuper = array_key_exists('is_super_admin', $attributes)
            ? (bool) $attributes['is_super_admin']
            : ($role instanceof Role && $role->is_super_admin);

        if ($parent->is_super_admin && ! $becomesSuper) {
            throw new RoleMatrixException('A role cannot inherit the Super Admin role.');
        }

        $parents = [];

        foreach (Role::query()->forGuard($guard)->get(['id', 'parent_id']) as $existing) {
            $parents[(string) $existing->getKey()] = $existing->parent_id !== null ? (string) $existing->parent_id : null;
        }

        $id = $role instanceof Role ? (string) $role->getKey() : 'new';
        $parents[$id] = (string) $parent->getKey();

        try {
            HierarchyWalker::ancestors($id, $parents, RoleMatrixConfig::maxDepth(), true);
        } catch (CircularHierarchyException) {
            throw new CircularHierarchyException('That parent would create a cycle in the role hierarchy.');
        }
    }

    private function reassign(Role $from, Role $to): void
    {
        if ($from->guard_name !== $to->guard_name) {
            throw new RoleMatrixException('Users can only be reassigned to a role on the same guard.');
        }

        $this->auditContext->withoutObservers(function () use ($from, $to): void {
            foreach (ModelRole::query()->where('role_id', $from->getKey())->get() as $assignment) {
                $duplicate = ModelRole::query()
                    ->where('role_id', $to->getKey())
                    ->where('model_type', $assignment->model_type)
                    ->where('model_id', $assignment->model_id)
                    ->where('scope_key', $assignment->scope_key)
                    ->exists();

                if ($duplicate) {
                    $assignment->delete();

                    continue;
                }

                $assignment->role_id = $to->getKey();
                $assignment->save();
            }
        });
    }
}
