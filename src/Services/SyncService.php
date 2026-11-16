<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use Illuminate\Support\Facades\DB;
use ImranDevBD\RoleMatrix\Contracts\SyncServiceContract;
use ImranDevBD\RoleMatrix\DTOs\SyncReport;
use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Events\ModulesSynced;
use ImranDevBD\RoleMatrix\Events\PermissionsSynced;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Models\RolePermission;
use ImranDevBD\RoleMatrix\Registry\ModuleRegistry;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class SyncService implements SyncServiceContract
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly AuditService $audit,
        private readonly CacheService $cache,
    ) {}

    public function sync(?string $guard = null, bool $prune = false, bool $dryRun = false): SyncReport
    {
        $guard ??= RoleMatrixConfig::defaultGuard();
        $this->registry->discover();

        $desired = [];

        foreach ($this->registry->visible($guard) as $module) {
            foreach ($module->actionsMap() as $action) {
                $desired[$action->key($module->slug())] = [
                    'module_slug' => $module->slug(),
                    'action' => $action->name,
                    'group' => $module->rawGroup(),
                    'description' => $action->description ?? $module->getDescription(),
                ];
            }
        }

        $existing = Permission::query()->forGuard($guard)->get()->keyBy('name');
        $added = [];
        $updated = [];
        $unchanged = [];
        $removed = [];

        foreach ($desired as $name => $meta) {
            $current = $existing->get($name);

            if (! $current instanceof Permission) {
                $added[] = $name;

                continue;
            }

            $same = $current->module_slug === $meta['module_slug']
                && $current->action === $meta['action']
                && (string) $current->group === (string) $meta['group']
                && (string) $current->description === (string) $meta['description'];

            if ($same) {
                $unchanged[] = $name;
            } else {
                $updated[] = $name;
            }
        }

        foreach ($existing as $permission) {
            $source = is_array($permission->meta) ? ($permission->meta['source'] ?? null) : null;

            if ($source === 'registry' && ! isset($desired[$permission->name])) {
                $removed[] = $permission->name;
            }
        }

        $report = new SyncReport($guard, $added, $prune ? $removed : [], $unchanged, $updated, $dryRun);

        if ($dryRun) {
            return $report;
        }

        DB::transaction(function () use ($guard, $desired, $added, $updated, $removed, $prune): void {
            foreach (array_merge($added, $updated) as $name) {
                $meta = $desired[$name];
                Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => $guard],
                    [
                        'module_slug' => $meta['module_slug'],
                        'action' => $meta['action'],
                        'group' => $meta['group'],
                        'description' => $meta['description'],
                        'is_system' => $meta['module_slug'] === 'role-matrix',
                        'meta' => ['source' => 'registry'],
                    ],
                );
            }

            if ($prune && $removed !== []) {
                Permission::query()->forGuard($guard)->whereIn('name', $removed)->delete();
            }

            $this->grantSuperAdmins($guard);
        });

        $this->registry->forgetCompiledCache();
        $this->cache->forgetAll();
        $this->audit->record(AuditEvent::ModulesSynced, null, null, null, $report->toArray());
        event(new ModulesSynced($guard, $report->toArray()));
        event(new PermissionsSynced($guard, $report->toArray()));

        return $report;
    }

    private function grantSuperAdmins(string $guard): void
    {
        $permissionIds = Permission::query()->forGuard($guard)->pluck('id');
        $roles = Role::query()->forGuard($guard)->where('is_super_admin', true)->get();

        foreach ($roles as $role) {
            $have = RolePermission::query()->where('role_id', $role->getKey())->pluck('permission_id')->map(fn (mixed $id): string => (string) $id)->all();

            foreach ($permissionIds as $permissionId) {
                if (in_array((string) $permissionId, $have, true)) {
                    continue;
                }

                RolePermission::query()->create([
                    'role_id' => $role->getKey(),
                    'permission_id' => $permissionId,
                    'conditions' => null,
                    'granted_at' => now(),
                    'granted_by' => null,
                ]);
            }
        }
    }
}
