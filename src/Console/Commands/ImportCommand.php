<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use ImranDevBD\RoleMatrix\Contracts\ImportExportServiceContract;
use ImranDevBD\RoleMatrix\Exceptions\ImportValidationException;

final class ImportCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:import
        {path : JSON file}
        {--mode=merge : merge, replace, or dry-run}
        {--create-missing : Create permissions that are not in the database}
        {--json : Machine-readable output}';

    protected $description = 'Import a Role Matrix JSON document';

    public function handle(ImportExportServiceContract $service): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            $this->components->error('The file is not valid JSON.');

            return self::FAILURE;
        }

        try {
            $report = $service->import($decoded, (string) $this->option('mode'), (bool) $this->option('create-missing'));
        } catch (ImportValidationException $exception) {
            if ($this->wantsJson()) {
                $this->line((string) json_encode(['errors' => $exception->errors], JSON_PRETTY_PRINT));
            } else {
                $this->components->error($exception->getMessage());
                foreach ($exception->errors as $error) {
                    $this->line(' - '.$error);
                }
            }

            return self::FAILURE;
        }

        if ($this->wantsJson()) {
            return $this->emit($report);
        }

        $created = is_array($report['created'] ?? null) ? $report['created'] : [];
        $unknown = is_array($report['unknown_permissions'] ?? null) ? $report['unknown_permissions'] : [];
        $this->components->info(($report['applied'] ?? false) ? 'Import applied.' : 'Dry run only.');
        $this->line('Created: '.($created === [] ? 'none' : implode(', ', $created)));
        $this->line('Unknown permissions: '.($unknown === [] ? 'none' : implode(', ', $unknown)));

        return self::SUCCESS;
    }
}
