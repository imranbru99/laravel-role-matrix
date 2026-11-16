<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use ImranDevBD\RoleMatrix\Registry\ModuleRegistry;

final class CacheCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:cache {--json : Machine-readable output}';

    protected $description = 'Write the compiled module registry cache';

    public function handle(ModuleRegistry $registry): int
    {
        $registry->discover();
        $registry->writeCompiledCache();

        if ($this->wantsJson()) {
            return $this->emit(['cached' => count($registry->all()), 'path' => (string) config('role-matrix.modules.cache_path')]);
        }

        $this->components->info('Module registry cached ('.count($registry->all()).' modules).');

        return self::SUCCESS;
    }
}
