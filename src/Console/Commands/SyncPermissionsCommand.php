<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use ImranDevBD\RoleMatrix\Contracts\SyncServiceContract;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class SyncPermissionsCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:sync-permissions
        {--dry-run : Show the diff without writing}
        {--prune : Delete registry permissions that are no longer declared}
        {--guard= : Limit the sync to one guard}
        {--force : Reserved for non-interactive callers}
        {--json : Machine-readable output}';

    protected $description = 'Create and update permissions from the module registry';

    public function handle(SyncServiceContract $sync): int
    {
        $guard = $this->option('guard');
        $guards = is_string($guard) && $guard !== '' ? [$guard] : RoleMatrixConfig::guards();
        $reports = [];

        foreach ($guards as $name) {
            $report = $sync->sync($name, (bool) $this->option('prune'), (bool) $this->option('dry-run'));
            $reports[] = $report->toArray();

            if (! $this->wantsJson()) {
                $this->components->info($name.': +'.count($report->added).' ~'.count($report->updated).' -'.count($report->removed).' ='.count($report->unchanged).($report->dryRun ? ' (dry run)' : ''));
            }
        }

        return $this->wantsJson() ? $this->emit(['reports' => $reports]) : self::SUCCESS;
    }
}
