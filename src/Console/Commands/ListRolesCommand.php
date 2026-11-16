<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use ImranDevBD\RoleMatrix\Models\Role;

final class ListRolesCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:list-roles {--guard= : Filter by guard} {--json : Machine-readable output}';

    protected $description = 'List roles';

    public function handle(): int
    {
        $query = Role::query()->withCount('permissions')->orderByDesc('level');

        if (is_string($this->option('guard')) && $this->option('guard') !== '') {
            $query->forGuard((string) $this->option('guard'));
        }

        $rows = $query->get()->map(fn (Role $role): array => [
            'name' => $role->name,
            'slug' => $role->slug,
            'guard' => $role->guard_name,
            'level' => $role->level,
            'permissions' => $role->permissions_count,
            'super' => $role->is_super_admin,
            'status' => $role->status instanceof \ImranDevBD\RoleMatrix\Enums\RoleStatus ? $role->status->value : (string) $role->status,
        ])->all();

        if ($this->wantsJson()) {
            return $this->emit($rows);
        }

        $this->table(['Name', 'Slug', 'Guard', 'Level', 'Permissions', 'Super', 'Status'], array_map(fn (array $row): array => [
            $row['name'], $row['slug'], $row['guard'], $row['level'], $row['permissions'], $row['super'] ? 'yes' : '', $row['status'],
        ], $rows));

        return self::SUCCESS;
    }
}
