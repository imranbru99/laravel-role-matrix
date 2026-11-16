<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use ImranDevBD\RoleMatrix\Contracts\AuditServiceContract;

final class PruneAuditCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:prune-audit {--days= : Delete entries older than this many days} {--json : Machine-readable output}';

    protected $description = 'Delete audit rows past the retention window';

    public function handle(AuditServiceContract $audit): int
    {
        $days = $this->option('days');
        $days = is_numeric($days) ? (int) $days : (int) config('role-matrix.audit.retention_days', 90);
        $deleted = $audit->prune($days);

        if ($this->wantsJson()) {
            return $this->emit(['deleted' => $deleted, 'days' => $days]);
        }

        $this->components->info("Deleted {$deleted} audit rows older than {$days} days.");

        return self::SUCCESS;
    }
}
