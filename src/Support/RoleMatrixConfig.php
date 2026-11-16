<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

use ImranDevBD\RoleMatrix\Enums\KeyType;

final class RoleMatrixConfig
{
    public const VERSION = '1.0.0';

    public const SCHEMA = 'role-matrix/1';

    public static function engine(): string
    {
        return (string) config('role-matrix.engine', 'native');
    }

    public static function keyType(): KeyType
    {
        return KeyType::fromConfig();
    }

    public static function table(string $name): string
    {
        $table = (string) config("role-matrix.table_names.{$name}", $name);
        $prefix = (string) config('role-matrix.table_names.prefix', '');

        return $prefix.$table;
    }

    /**
     * @return class-string
     */
    public static function model(string $name): string
    {
        /** @var class-string $class */
        $class = (string) config("role-matrix.models.{$name}");

        return $class;
    }

    /**
     * @return class-string<\ImranDevBD\RoleMatrix\Models\Role>
     */
    public static function roleClass(): string
    {
        $class = config('role-matrix.models.role', \ImranDevBD\RoleMatrix\Models\Role::class);

        return is_string($class) && is_a($class, \ImranDevBD\RoleMatrix\Models\Role::class, true)
            ? $class
            : \ImranDevBD\RoleMatrix\Models\Role::class;
    }

    /**
     * @return class-string<\ImranDevBD\RoleMatrix\Models\Permission>
     */
    public static function permissionClass(): string
    {
        $class = config('role-matrix.models.permission', \ImranDevBD\RoleMatrix\Models\Permission::class);

        return is_string($class) && is_a($class, \ImranDevBD\RoleMatrix\Models\Permission::class, true)
            ? $class
            : \ImranDevBD\RoleMatrix\Models\Permission::class;
    }

    /**
     * @return class-string<\ImranDevBD\RoleMatrix\Models\ModelRole>
     */
    public static function modelRoleClass(): string
    {
        $class = config('role-matrix.models.model_role', \ImranDevBD\RoleMatrix\Models\ModelRole::class);

        return is_string($class) && is_a($class, \ImranDevBD\RoleMatrix\Models\ModelRole::class, true)
            ? $class
            : \ImranDevBD\RoleMatrix\Models\ModelRole::class;
    }

    /**
     * @return class-string<\ImranDevBD\RoleMatrix\Models\RolePermission>
     */
    public static function rolePermissionClass(): string
    {
        $class = config('role-matrix.models.role_permission', \ImranDevBD\RoleMatrix\Models\RolePermission::class);

        return is_string($class) && is_a($class, \ImranDevBD\RoleMatrix\Models\RolePermission::class, true)
            ? $class
            : \ImranDevBD\RoleMatrix\Models\RolePermission::class;
    }

    /**
     * @return class-string
     */
    public static function service(string $name): string
    {
        /** @var class-string $class */
        $class = (string) config("role-matrix.services.{$name}");

        return $class;
    }

    public static function defaultGuard(): string
    {
        return (string) config('role-matrix.guards.default', 'web');
    }

    /**
     * @return list<string>
     */
    public static function guards(): array
    {
        $guards = config('role-matrix.guards.allowed', ['web']);

        if (! is_array($guards) || $guards === []) {
            return [self::defaultGuard()];
        }

        return array_values(array_map(strval(...), $guards));
    }

    public static function superAdminSlug(): string
    {
        return (string) config('role-matrix.super_admin.slug', 'super-admin');
    }

    public static function superAdminName(): string
    {
        return (string) config('role-matrix.super_admin.name', 'Super Admin');
    }

    public static function hierarchyEnabled(): bool
    {
        return (bool) config('role-matrix.hierarchy.enabled', true);
    }

    public static function maxDepth(): int
    {
        return max(1, (int) config('role-matrix.hierarchy.max_depth', 10));
    }

    public static function wildcardsEnabled(): bool
    {
        return (bool) config('role-matrix.wildcards.enabled', true);
    }

    public static function abacEnabled(): bool
    {
        return (bool) config('role-matrix.abac.enabled', true);
    }

    public static function abacStrict(): bool
    {
        return (bool) config('role-matrix.abac.strict', false);
    }

    public static function ownerKey(): string
    {
        return (string) config('role-matrix.abac.owner_key', 'user_id');
    }

    public static function teamsEnabled(): bool
    {
        return (bool) config('role-matrix.teams.enabled', false);
    }

    public static function directPermissionsEnabled(): bool
    {
        return (bool) config('role-matrix.direct_permissions.enabled', true);
    }

    public static function temporaryRolesEnabled(): bool
    {
        return (bool) config('role-matrix.temporary_roles.enabled', true);
    }

    public static function cacheTtl(): int
    {
        return max(1, (int) config('role-matrix.cache.ttl', 3600));
    }

    public static function cachePrefix(): string
    {
        return (string) config('role-matrix.cache.prefix', 'role-matrix');
    }

    public static function cacheStore(): ?string
    {
        $store = config('role-matrix.cache.store');

        return is_string($store) && $store !== '' ? $store : null;
    }

    /**
     * @return list<string>
     */
    public static function dangerousActions(): array
    {
        $actions = config('role-matrix.modules.dangerous_actions', []);

        return is_array($actions) ? array_values(array_map(strval(...), $actions)) : [];
    }
}
