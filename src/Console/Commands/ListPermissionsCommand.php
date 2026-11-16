<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use ImranDevBD\RoleMatrix\Models\Permission;

final class ListPermissionsCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:list-permissions {--module= : Filter by module slug} {--guard= : Filter by guard} {--json : Machine-readable output}';

    protected $description = 'List permissions';

    public function handle(): int
    {
        $query = Permission::query()->orderBy('name');

        if (is_string($this->option('module')) && $this->option('module') !== '') {
            $query->where('module_slug', $this->option('module'));
        }

        if (is_string($this->option('guard')) && $this->option('guard') !== '') {
            $query->forGuard((string) $this->option('guard'));
        }

        $rows = $query->get(['name', 'guard_name', 'module_slug', 'action', 'group'])->toArray();

        if ($this->wantsJson()) {
            return $this->emit($rows);
        }

        $this->table(['Name', 'Guard', 'Module', 'Action', 'Group'], array_map(fn (array $row): array => [
            $row['name'], $row['guard_name'], $row['module_slug'], $row['action'], $row['group'],
        ], $rows));

        return self::SUCCESS;
    }
}
