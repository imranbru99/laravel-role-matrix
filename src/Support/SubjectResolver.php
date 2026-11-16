<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

use BackedEnum;
use Illuminate\Support\Str;
use ImranDevBD\RoleMatrix\Exceptions\RoleMatrixException;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;

final class SubjectResolver
{
    public function role(mixed $value, ?string $guard = null): Role
    {
        /** @var class-string<Role> $model */
        $model = RoleMatrixConfig::model('role');
        $guard ??= RoleMatrixConfig::defaultGuard();

        if ($value instanceof Role) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            /** @var Role $role */
            $role = $model::query()->findOrFail($value);

            return $role;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new RoleMatrixException('A role reference must be a model, id, slug, or name.');
        }

        $needle = trim($value);
        $role = $model::query()
            ->forGuard($guard)
            ->where(function ($query) use ($needle): void {
                $query->where('slug', $needle)
                    ->orWhere('slug', Str::slug($needle))
                    ->orWhere('name', $needle);
            })
            ->first();

        if (! $role instanceof Role) {
            throw new RoleMatrixException("Role [{$needle}] was not found for guard [{$guard}].");
        }

        return $role;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<Role>
     */
    public function roles(array $values, ?string $guard = null): array
    {
        $roles = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $roles = array_merge($roles, $this->roles($value, $guard));

                continue;
            }

            $roles[] = $this->role($value, $guard);
        }

        return $roles;
    }

    public function permission(mixed $value, ?string $guard = null, bool $create = false): Permission
    {
        /** @var class-string<Permission> $model */
        $model = RoleMatrixConfig::model('permission');
        $guard ??= RoleMatrixConfig::defaultGuard();

        if ($value instanceof Permission) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            /** @var Permission $permission */
            $permission = $model::query()->findOrFail($value);

            return $permission;
        }

        $name = PermissionKey::normalize($value);

        if (! PermissionKey::isConcrete($name) && ! PermissionKey::isPattern($name)) {
            throw new RoleMatrixException("Permission [{$name}] is not a valid module.action key.");
        }

        $existing = $model::query()->forGuard($guard)->where('name', $name)->first();

        if ($existing instanceof Permission) {
            return $existing;
        }

        if (! $create) {
            throw new RoleMatrixException("Permission [{$name}] does not exist for guard [{$guard}].");
        }

        return $model::query()->create([
            'name' => $name,
            'guard_name' => $guard,
            'module_slug' => PermissionKey::isPattern($name) ? PermissionKey::module($name) : PermissionKey::module($name),
            'action' => PermissionKey::action($name),
            'is_system' => false,
            'meta' => ['source' => 'manual'],
        ]);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<Permission>
     */
    public function permissions(array $values, ?string $guard = null, bool $create = false): array
    {
        $permissions = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $permissions = array_merge($permissions, $this->permissions($value, $guard, $create));

                continue;
            }

            $permissions[] = $this->permission($value, $guard, $create);
        }

        return $permissions;
    }
}
