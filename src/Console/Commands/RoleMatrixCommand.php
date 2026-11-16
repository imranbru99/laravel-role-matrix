<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use Illuminate\Console\Command;

abstract class RoleMatrixCommand extends Command
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    protected function emit(array $data): int
    {
        if ($this->hasOption('json') && $this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }

    protected function wantsJson(): bool
    {
        return $this->hasOption('json') && (bool) $this->option('json');
    }
}
