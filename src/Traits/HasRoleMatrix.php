<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract;
use ImranDevBD\RoleMatrix\Enums\PermissionEffect;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Services\AssignmentService;
use ImranDevBD\RoleMatrix\Support\PermissionKey;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\SubjectResolver;

trait HasRoleMatrix
{
    public static function bootHasRoleMatrix(): void
    {
        static::created(function (Model $user): void {
            if (! method_exists($user, 'assignRole')) {
                return;
            }

            app(AssignmentService::class)->assignDefaults($user);
        });
    }

    public function roleMatrixGuard(): string
    {
        $guard = $this->getAttribute('guard_name');

        if (is_string($guard) && $guard !== '') {
            return $guard;
        }

        $current = auth()->getDefaultDriver();

        if (in_array($current, RoleMatrixConfig::guards(), true)) {
            return $current;
        }

        return RoleMatrixConfig::defaultGuard();
    }

    /**
     * @return MorphToMany<Role, $this>
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            RoleMatrixConfig::roleClass(),
            'model',
            RoleMatrixConfig::table('model_has_roles'),
            'model_id',
            'role_id',
        )->withPivot(['team_id', 'scope_key', 'expires_at', 'assigned_by'])->withTimestamps();
    }

    /**
     * @return MorphToMany<Permission, $this>
     */
    public function directPermissions(): MorphToMany
    {
        return $this->morphToMany(
            RoleMatrixConfig::permissionClass(),
            'model',
            RoleMatrixConfig::table('model_has_permissions'),
            'model_id',
            'permission_id',
        )->withPivot(['effect', 'conditions', 'expires_at', 'team_id', 'scope_key', 'assigned_by'])->withTimestamps();
    }

    public function assignRole(mixed ...$roles): static
    {
        app(AssignmentService::class)->assign($this, $roles, null, $this->roleMatrixGuard());

        return $this;
    }

    public function assignTemporaryRole(mixed $role, \DateTimeInterface $expiresAt): static
    {
        app(AssignmentService::class)->assign($this, [$role], $expiresAt, $this->roleMatrixGuard());

        return $this;
    }

    public function syncRoles(mixed ...$roles): static
    {
        app(AssignmentService::class)->sync($this, $roles, $this->roleMatrixGuard());

        return $this;
    }

    public function removeRole(mixed ...$roles): static
    {
        app(AssignmentService::class)->remove($this, $roles, $this->roleMatrixGuard());

        return $this;
    }

    public function hasRole(mixed $roles, ?string $guard = null): bool
    {
        $guard ??= $this->roleMatrixGuard();
        $names = $this->activeRoleNames($guard);

        foreach ($this->roleNeedles($roles) as $needle) {
            if (in_array($needle, $names, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyRole(mixed ...$roles): bool
    {
        $names = $this->activeRoleNames($this->roleMatrixGuard());

        foreach ($this->roleNeedles($roles) as $needle) {
            if (in_array($needle, $names, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllRoles(mixed ...$roles): bool
    {
        $needles = $this->roleNeedles($roles);

        if ($needles === []) {
            return false;
        }

        $names = $this->activeRoleNames($this->roleMatrixGuard());

        foreach ($needles as $needle) {
            if (! in_array($needle, $names, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection
    {
        return collect($this->activeRoleNames($this->roleMatrixGuard()));
    }

    /**
     * @return Collection<int, Role>
     */
    public function rolesWithInherited(): Collection
    {
        $effective = app(PermissionResolverContract::class)->forUser($this);
        $names = $effective->roleNames;

        if ($names === []) {
            return new Collection;
        }

        $model = RoleMatrixConfig::roleClass();

        return $model::query()->forGuard($this->roleMatrixGuard())->whereIn('name', $names)->get();
    }

    public function givePermissionTo(mixed ...$permissions): static
    {
        app(AssignmentService::class)->give($this, $permissions, null, PermissionEffect::Allow->value);

        return $this;
    }

    public function denyPermissionTo(mixed ...$permissions): static
    {
        app(AssignmentService::class)->give($this, $permissions, null, PermissionEffect::Deny->value);

        return $this;
    }

    public function revokePermissionTo(mixed ...$permissions): static
    {
        app(AssignmentService::class)->revoke($this, $permissions);

        return $this;
    }

    public function syncPermissions(mixed ...$permissions): static
    {
        app(AssignmentService::class)->syncDirect($this, $permissions);

        return $this;
    }

    public function hasPermissionTo(mixed $permission, mixed $record = null): bool
    {
        if ($permission instanceof Permission) {
            $permission = $permission->name;
        }

        return app(PermissionResolverContract::class)->allows($this, PermissionKey::normalize($permission), $record, $this->roleMatrixGuard());
    }

    public function hasAnyPermission(mixed ...$permissions): bool
    {
        foreach ($this->permissionNeedles($permissions) as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(mixed ...$permissions): bool
    {
        $needles = $this->permissionNeedles($permissions);

        if ($needles === []) {
            return false;
        }

        foreach ($needles as $permission) {
            if (! $this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, string>
     */
    public function getAllPermissions(): Collection
    {
        $effective = app(PermissionResolverContract::class)->forUser($this, $this->roleMatrixGuard());

        return collect(array_keys($effective->exactAllows));
    }

    /**
     * @return Collection<int, Permission>
     */
    public function getDirectPermissions(): Collection
    {
        return $this->directPermissions()->get();
    }

    public function isSuperAdmin(): bool
    {
        return app(PermissionResolverContract::class)->forUser($this, $this->roleMatrixGuard())->superAdmin;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeRole(Builder $query, mixed $role, ?string $guard = null): Builder
    {
        $resolved = app(SubjectResolver::class)->role($role, $guard ?? RoleMatrixConfig::defaultGuard());
        $table = $query->getModel()->getTable();

        return $query->whereExists(function ($sub) use ($resolved, $table, $query): void {
            $sub->selectRaw('1')
                ->from(RoleMatrixConfig::table('model_has_roles').' as rm_roles')
                ->whereColumn('rm_roles.model_id', $table.'.'.$query->getModel()->getKeyName())
                ->where('rm_roles.model_type', $query->getModel()->getMorphClass())
                ->where('rm_roles.role_id', $resolved->getKey())
                ->where(function ($inner): void {
                    $inner->whereNull('rm_roles.expires_at')->orWhere('rm_roles.expires_at', '>', now());
                });
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopePermission(Builder $query, mixed $permission, ?string $guard = null): Builder
    {
        $name = $permission instanceof Permission ? $permission->name : PermissionKey::normalize($permission);
        $patterns = PermissionKey::covering($name);
        $guard ??= RoleMatrixConfig::defaultGuard();
        $users = $query->getModel()->getTable();
        $key = $query->getModel()->getKeyName();
        $type = $query->getModel()->getMorphClass();

        return $query->where(function (Builder $outer) use ($patterns, $guard, $users, $key, $type): void {
            $outer->whereExists(function ($sub) use ($patterns, $guard, $users, $key, $type): void {
                $sub->selectRaw('1')
                    ->from(RoleMatrixConfig::table('model_has_roles').' as rm_roles')
                    ->join(RoleMatrixConfig::table('role_has_permissions').' as rm_grants', 'rm_grants.role_id', '=', 'rm_roles.role_id')
                    ->join(RoleMatrixConfig::table('permissions').' as rm_permissions', 'rm_permissions.id', '=', 'rm_grants.permission_id')
                    ->whereColumn('rm_roles.model_id', $users.'.'.$key)
                    ->where('rm_roles.model_type', $type)
                    ->where('rm_permissions.guard_name', $guard)
                    ->whereIn('rm_permissions.name', $patterns)
                    ->where(function ($inner): void {
                        $inner->whereNull('rm_roles.expires_at')->orWhere('rm_roles.expires_at', '>', now());
                    });
            })->orWhereExists(function ($sub) use ($patterns, $guard, $users, $key, $type): void {
                $sub->selectRaw('1')
                    ->from(RoleMatrixConfig::table('model_has_permissions').' as rm_direct')
                    ->join(RoleMatrixConfig::table('permissions').' as rm_permissions', 'rm_permissions.id', '=', 'rm_direct.permission_id')
                    ->whereColumn('rm_direct.model_id', $users.'.'.$key)
                    ->where('rm_direct.model_type', $type)
                    ->where('rm_direct.effect', PermissionEffect::Allow->value)
                    ->where('rm_permissions.guard_name', $guard)
                    ->whereIn('rm_permissions.name', $patterns);
            });
        })->whereNotExists(function ($sub) use ($patterns, $guard, $users, $key, $type): void {
            $sub->selectRaw('1')
                ->from(RoleMatrixConfig::table('model_has_permissions').' as rm_deny')
                ->join(RoleMatrixConfig::table('permissions').' as rm_permissions', 'rm_permissions.id', '=', 'rm_deny.permission_id')
                ->whereColumn('rm_deny.model_id', $users.'.'.$key)
                ->where('rm_deny.model_type', $type)
                ->where('rm_deny.effect', PermissionEffect::Deny->value)
                ->where('rm_permissions.guard_name', $guard)
                ->whereIn('rm_permissions.name', $patterns)
                ->whereNull('rm_deny.conditions');
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWithoutRole(Builder $query, mixed $role = null): Builder
    {
        $users = $query->getModel()->getTable();
        $key = $query->getModel()->getKeyName();
        $type = $query->getModel()->getMorphClass();

        if ($role === null) {
            return $query->whereNotExists(function ($sub) use ($users, $key, $type): void {
                $sub->selectRaw('1')
                    ->from(RoleMatrixConfig::table('model_has_roles').' as rm_roles')
                    ->whereColumn('rm_roles.model_id', $users.'.'.$key)
                    ->where('rm_roles.model_type', $type)
                    ->where(function ($inner): void {
                        $inner->whereNull('rm_roles.expires_at')->orWhere('rm_roles.expires_at', '>', now());
                    });
            });
        }

        $resolved = app(SubjectResolver::class)->role($role, RoleMatrixConfig::defaultGuard());

        return $query->whereNotExists(function ($sub) use ($resolved, $users, $key, $type): void {
            $sub->selectRaw('1')
                ->from(RoleMatrixConfig::table('model_has_roles').' as rm_roles')
                ->whereColumn('rm_roles.model_id', $users.'.'.$key)
                ->where('rm_roles.model_type', $type)
                ->where('rm_roles.role_id', $resolved->getKey());
        });
    }

    /**
     * @return list<string>
     */
    private function activeRoleNames(string $guard): array
    {
        $rows = ModelRole::query()
            ->active()
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', (string) $this->getKey())
            ->whereHas('role', function (Builder $query) use ($guard): void {
                $query->where('guard_name', $guard)->where('status', 'active');
            })
            ->with('role:id,name,slug,guard_name')
            ->get();

        $names = [];

        foreach ($rows as $row) {
            if ($row->role instanceof Role) {
                $names[] = $row->role->name;
                $names[] = $row->role->slug;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function roleNeedles(mixed $roles): array
    {
        $values = is_array($roles) ? $roles : [$roles];
        $needles = [];

        $walker = function (mixed $value) use (&$walker, &$needles): void {
            if ($value instanceof Role) {
                $needles[] = $value->name;
                $needles[] = $value->slug;

                return;
            }

            if ($value instanceof BackedEnum) {
                $needles[] = (string) $value->value;

                return;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $walker($item);
                }

                return;
            }

            if (is_string($value) && $value !== '') {
                $needles[] = $value;
            }
        };

        foreach ($values as $value) {
            $walker($value);
        }

        return array_values(array_unique($needles));
    }

    /**
     * @return list<string>
     */
    private function permissionNeedles(mixed $permissions): array
    {
        $values = is_array($permissions) ? $permissions : [$permissions];
        $needles = [];

        $walker = function (mixed $value) use (&$walker, &$needles): void {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $walker($item);
                }

                return;
            }

            if ($value instanceof Permission) {
                $needles[] = $value->name;

                return;
            }

            if ($value instanceof BackedEnum || (is_string($value) && $value !== '')) {
                $needles[] = PermissionKey::normalize($value);
            }
        };

        foreach ($values as $value) {
            $walker($value);
        }

        return $needles;
    }
}
