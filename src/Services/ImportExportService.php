<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ImranDevBD\RoleMatrix\Contracts\ImportExportServiceContract;
use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Enums\ImportMode;
use ImranDevBD\RoleMatrix\Enums\RoleStatus;
use ImranDevBD\RoleMatrix\Events\RolesImported;
use ImranDevBD\RoleMatrix\Exceptions\ImportValidationException;
use ImranDevBD\RoleMatrix\Exceptions\SuperAdminProtectedException;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Models\RolePermission;
use ImranDevBD\RoleMatrix\Registry\ModuleRegistry;
use ImranDevBD\RoleMatrix\Support\Checksum;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class ImportExportService implements ImportExportServiceContract
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly AuditService $audit,
        private readonly CacheService $cache,
        private readonly SuperAdminGuard $guard,
    ) {}

    public function export(?string $guard = null, ?array $roleSlugs = null): array
    {
        $guard ??= RoleMatrixConfig::defaultGuard();
        $query = Role::query()->forGuard($guard)->with(['permissions', 'parent'])->orderByDesc('level');

        if ($roleSlugs !== null && $roleSlugs !== []) {
            $query->whereIn('slug', $roleSlugs);
        }

        $roles = [];

        foreach ($query->get() as $role) {
            $permissions = [];

            foreach ($role->permissions as $permission) {
                $pivot = $permission->getRelation('pivot');
                $permissions[$permission->name] = [
                    'conditions' => $pivot instanceof RolePermission ? $pivot->conditions : null,
                ];
            }

            $roles[] = [
                'name' => $role->name,
                'slug' => $role->slug,
                'guard_name' => $role->guard_name,
                'parent' => $role->parent?->slug,
                'description' => $role->description,
                'color' => $role->color,
                'icon' => $role->icon,
                'level' => $role->level,
                'is_default' => $role->is_default,
                'is_super_admin' => $role->is_super_admin,
                'is_system' => $role->is_system,
                'status' => $role->status instanceof RoleStatus ? $role->status->value : (string) $role->status,
                'permissions' => $permissions,
            ];
        }

        $payload = [
            'schema' => RoleMatrixConfig::SCHEMA,
            'exported_at' => now()->toIso8601String(),
            'app' => (string) config('app.name'),
            'version' => \ImranDevBD\RoleMatrix\RoleMatrix::VERSION,
            'guard' => $guard,
            'roles' => $roles,
            'modules' => array_map(static fn ($module) => $module->toArray(), $this->registry->visible($guard)),
        ];

        return Checksum::sign($payload);
    }

    public function import(array $payload, string $mode = 'merge', bool $createMissing = false): array
    {
        $importMode = ImportMode::tryFrom($mode) ?? throw new ImportValidationException('Mode must be merge, replace, or dry-run.');
        $errors = $this->validate($payload);

        if ($errors !== []) {
            throw new ImportValidationException('The import payload is invalid.', $errors);
        }

        $guard = (string) ($payload['guard'] ?? RoleMatrixConfig::defaultGuard());
        /** @var list<array<string, mixed>> $roles */
        $roles = $payload['roles'];
        $diff = $this->diff($guard, $roles, $createMissing);

        if ($importMode === ImportMode::Replace) {
            $this->assertReplaceKeepsSuperAdmin($guard, $roles);
        }

        if ($importMode === ImportMode::DryRun) {
            return ['mode' => $mode, 'applied' => false] + $diff;
        }

        DB::transaction(function () use ($guard, $roles, $importMode, $createMissing, $diff): void {
            if ($importMode === ImportMode::Replace) {
                $slugs = array_map(static fn (array $role): string => (string) $role['slug'], $roles);
                $doomed = Role::query()->forGuard($guard)->whereNotIn('slug', $slugs)->get();

                foreach ($doomed as $role) {
                    $this->guard->assertCanDelete($role);
                    $role->forceDelete();
                }
            }

            $bySlug = [];

            foreach ($roles as $row) {
                $bySlug[(string) $row['slug']] = $this->upsertRole($guard, $row);
            }

            foreach ($roles as $row) {
                $role = $bySlug[(string) $row['slug']];
                $parentSlug = $row['parent'] ?? null;

                if (is_string($parentSlug) && $parentSlug !== '' && isset($bySlug[$parentSlug]) && ! $role->is_super_admin) {
                    $role->parent_id = $bySlug[$parentSlug]->getKey();
                    $role->save();
                }

                $this->syncImportedPermissions($role, is_array($row['permissions'] ?? null) ? $row['permissions'] : [], $createMissing);
            }

            $this->audit->record(AuditEvent::RolesImported, null, null, null, ['mode' => $importMode->value, 'diff' => $diff]);
        });

        $this->cache->forgetAll();
        event(new RolesImported($importMode->value, $diff));

        return ['mode' => $mode, 'applied' => true] + $diff;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function validate(array $payload): array
    {
        $errors = [];

        if (($payload['schema'] ?? null) !== RoleMatrixConfig::SCHEMA) {
            $errors[] = 'Unsupported schema. Expected '.RoleMatrixConfig::SCHEMA.'.';
        }

        if (! Checksum::valid($payload)) {
            $errors[] = 'Checksum does not match the payload.';
        }

        if (! isset($payload['roles']) || ! is_array($payload['roles'])) {
            $errors[] = 'Roles must be a list.';
        }

        return $errors;
    }

    /**
     * @param  list<array<string, mixed>>  $roles
     * @return array{created: list<string>, updated: list<string>, removed: list<string>, unknown_permissions: list<string>}
     */
    private function diff(string $guard, array $roles, bool $createMissing): array
    {
        $created = [];
        $updated = [];
        $unknown = [];
        $slugs = [];

        foreach ($roles as $row) {
            $slug = (string) ($row['slug'] ?? '');
            $slugs[] = $slug;
            $exists = Role::query()->forGuard($guard)->where('slug', $slug)->exists();
            $exists ? $updated[] = $slug : $created[] = $slug;

            foreach (array_keys(is_array($row['permissions'] ?? null) ? $row['permissions'] : []) as $name) {
                $found = Permission::query()->forGuard($guard)->where('name', $name)->exists();

                if (! $found && ! $createMissing) {
                    $unknown[] = (string) $name;
                }
            }
        }

        $removed = Role::query()->forGuard($guard)->whereNotIn('slug', $slugs)->pluck('slug')->all();

        return [
            'created' => $created,
            'updated' => $updated,
            'removed' => array_values($removed),
            'unknown_permissions' => array_values(array_unique($unknown)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $roles
     */
    private function assertReplaceKeepsSuperAdmin(string $guard, array $roles): void
    {
        $incoming = collect($roles)->first(fn (array $role): bool => (bool) ($role['is_super_admin'] ?? false) || $role['slug'] === RoleMatrixConfig::superAdminSlug());
        $existing = Role::query()->forGuard($guard)->where('is_super_admin', true)->first();

        if ($existing instanceof Role && ! is_array($incoming)) {
            throw new SuperAdminProtectedException('Replace mode would remove the Super Admin role. The import was aborted.');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertRole(string $guard, array $row): Role
    {
        $slug = Str::slug((string) ($row['slug'] ?? $row['name'] ?? ''));
        $role = Role::query()->forGuard($guard)->where('slug', $slug)->first();
        $attributes = [
            'name' => (string) ($row['name'] ?? $slug),
            'description' => $row['description'] ?? null,
            'color' => $row['color'] ?? null,
            'icon' => $row['icon'] ?? null,
            'level' => (int) ($row['level'] ?? 0),
            'is_default' => (bool) ($row['is_default'] ?? false),
            'status' => (string) ($row['status'] ?? RoleStatus::Active->value),
        ];

        if ($role instanceof Role) {
            if ($role->is_super_admin) {
                unset($attributes['name']);
            }

            $role->fill($attributes);
            $role->save();

            return $role;
        }

        return Role::query()->create($attributes + [
            'slug' => $slug,
            'guard_name' => $guard,
            'is_super_admin' => (bool) ($row['is_super_admin'] ?? false),
            'is_system' => (bool) ($row['is_system'] ?? false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function syncImportedPermissions(Role $role, array $permissions, bool $createMissing): void
    {
        $ids = [];

        foreach ($permissions as $name => $meta) {
            $permission = Permission::query()->forGuard($role->guard_name)->where('name', $name)->first();

            if (! $permission instanceof Permission) {
                if (! $createMissing) {
                    continue;
                }

                $permission = Permission::query()->create([
                    'name' => (string) $name,
                    'guard_name' => $role->guard_name,
                    'module_slug' => \ImranDevBD\RoleMatrix\Support\PermissionKey::module((string) $name),
                    'action' => \ImranDevBD\RoleMatrix\Support\PermissionKey::action((string) $name),
                    'is_system' => false,
                    'meta' => ['source' => 'import'],
                ]);
            }

            $conditions = is_array($meta) ? ($meta['conditions'] ?? null) : null;

            if ($role->is_super_admin && is_array($conditions) && $conditions !== []) {
                $conditions = null;
            }

            RolePermission::query()->updateOrCreate(
                ['role_id' => $role->getKey(), 'permission_id' => $permission->getKey()],
                ['conditions' => $conditions, 'granted_at' => now()],
            );
            $ids[] = $permission->getKey();
        }

        if ($role->is_super_admin) {
            return;
        }

        RolePermission::query()
            ->where('role_id', $role->getKey())
            ->when($ids !== [], fn ($query) => $query->whereNotIn('permission_id', $ids))
            ->delete();
    }
}
