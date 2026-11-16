<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Spatie;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use ImranDevBD\RoleMatrix\Events\PermissionsSynced;
use ImranDevBD\RoleMatrix\Events\RoleAssignedToUser;
use ImranDevBD\RoleMatrix\Events\RoleRemovedFromUser;
use ImranDevBD\RoleMatrix\Exceptions\RoleMatrixException;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

/**
 * Mirrors native role and permission names into spatie/laravel-permission tables.
 * Native tables stay the source of truth. Conditions, hierarchy, and audit are not copied.
 */
final class SpatieCompatibilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            throw new RoleMatrixException('engine=spatie requires spatie/laravel-permission to be installed.');
        }

        $prefix = (string) config('role-matrix.table_names.prefix', '');
        $nativeRoles = $prefix.'roles';

        if ($nativeRoles === 'roles' && Schema::hasTable('roles')) {
            throw new RoleMatrixException('Spatie and Role Matrix would share the roles table. Set ROLE_MATRIX_TABLE_PREFIX=rm_ before enabling the bridge.');
        }

        $this->app['events']->listen(PermissionsSynced::class, function (): void {
            $this->mirror();
        });
        $this->app['events']->listen(RoleAssignedToUser::class, function (): void {
            $this->mirror();
        });
        $this->app['events']->listen(RoleRemovedFromUser::class, function (): void {
            $this->mirror();
        });
    }

    public function mirror(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        foreach (RoleMatrixConfig::guards() as $guard) {
            foreach (Role::query()->forGuard($guard)->get() as $role) {
                \Spatie\Permission\Models\Role::query()->firstOrCreate([
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                ]);
            }

            foreach (Permission::query()->forGuard($guard)->get() as $permission) {
                \Spatie\Permission\Models\Permission::query()->firstOrCreate([
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ]);
            }
        }
    }
}
