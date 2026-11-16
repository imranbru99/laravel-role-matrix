<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use Illuminate\Support\Facades\Cache;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Registry\ModuleRegistry;
use ImranDevBD\RoleMatrix\Support\HierarchyWalker;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class DoctorCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:doctor {--json : Machine-readable output}';

    protected $description = 'Diagnose missing super admins, unsynced modules, cycles, and cache support';

    public function handle(ModuleRegistry $registry): int
    {
        $registry->discover();
        $checks = [];

        $superRoles = Role::query()->where('is_super_admin', true)->count();
        $checks[] = $this->check('super-admin-role', $superRoles > 0, $superRoles > 0 ? 'Super Admin role exists.' : 'No Super Admin role. Run role-matrix:install or create-super-admin.');

        $superUsers = ModelRole::query()->active()->whereHas('role', fn ($query) => $query->where('is_super_admin', true))->count();
        $checks[] = $this->check('super-admin-user', $superUsers > 0, $superUsers > 0 ? "{$superUsers} super admin assignment(s)." : 'No user holds the Super Admin role.', $superUsers === 0 ? 'warn' : 'ok');

        $declared = $registry->permissionNames();
        $stored = Permission::query()->where('meta->source', 'registry')->pluck('name')->all();
        $missing = array_values(array_diff($declared, $stored));
        $checks[] = $this->check('unsynced-modules', $missing === [], $missing === [] ? 'Registry permissions are synced.' : 'Unsynced: '.implode(', ', array_slice($missing, 0, 8)), $missing === [] ? 'ok' : 'warn');

        $orphans = array_values(array_diff($stored, $declared));
        $checks[] = $this->check('orphan-permissions', $orphans === [], $orphans === [] ? 'No orphaned registry permissions.' : 'Orphans: '.implode(', ', array_slice($orphans, 0, 8)), $orphans === [] ? 'ok' : 'warn');

        $parents = [];
        foreach (Role::query()->get(['id', 'parent_id']) as $role) {
            $parents[(string) $role->getKey()] = $role->parent_id !== null ? (string) $role->parent_id : null;
        }
        $cycles = HierarchyWalker::cycles($parents);
        $checks[] = $this->check('hierarchy-cycles', $cycles === [], $cycles === [] ? 'Role hierarchy has no cycles.' : 'Cycles detected.');

        $allowed = RoleMatrixConfig::guards();
        $foreign = Permission::query()->whereNotIn('guard_name', $allowed)->count();
        $checks[] = $this->check('guards', $foreign === 0, $foreign === 0 ? 'Permission guards match the allow-list.' : "{$foreign} permissions use a guard outside the allow-list.", $foreign === 0 ? 'ok' : 'warn');

        try {
            $supports = Cache::getStore() instanceof \Illuminate\Cache\TaggableStore;
        } catch (\Throwable) {
            $supports = false;
        }
        $checks[] = $this->check('cache-tags', true, $supports ? 'Cache store supports tags.' : 'Cache store has no tags. Versioned keys are used instead.', 'ok');

        $failed = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'fail');

        if ($this->wantsJson()) {
            $this->line((string) json_encode(['ok' => ! $failed, 'checks' => $checks], JSON_PRETTY_PRINT));

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['Check', 'Status', 'Detail'], array_map(fn (array $check): array => [
            $check['name'], strtoupper($check['status']), $check['message'],
        ], $checks));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function check(string $name, bool $ok, string $message, string $status = ''): array
    {
        return [
            'name' => $name,
            'status' => $status !== '' ? $status : ($ok ? 'ok' : 'fail'),
            'message' => $message,
        ];
    }
}
