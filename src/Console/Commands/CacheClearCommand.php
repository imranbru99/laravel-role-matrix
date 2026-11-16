<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use ImranDevBD\RoleMatrix\Registry\ModuleRegistry;
use ImranDevBD\RoleMatrix\Services\CacheService;

final class CacheClearCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:cache:clear {--json : Machine-readable output}';

    protected $description = 'Forget compiled modules and cached effective permissions';

    public function handle(ModuleRegistry $registry, CacheService $cache): int
    {
        $registry->forgetCompiledCache();
        $cache->forgetAll();

        if ($this->wantsJson()) {
            return $this->emit(['cleared' => true]);
        }

        $this->components->info('Role Matrix cache cleared.');

        return self::SUCCESS;
    }
}
