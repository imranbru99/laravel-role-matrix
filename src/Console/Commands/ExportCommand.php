<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use Illuminate\Support\Facades\File;
use ImranDevBD\RoleMatrix\Contracts\ImportExportServiceContract;

final class ExportCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:export
        {--role=* : Role slugs to include}
        {--path= : Destination JSON file}
        {--guard=web : Guard}
        {--json : Machine-readable output}';

    protected $description = 'Export roles, grants, and conditions to JSON';

    public function handle(ImportExportServiceContract $service): int
    {
        $roles = $this->option('role');
        $payload = $service->export(
            (string) $this->option('guard'),
            is_array($roles) && $roles !== [] ? array_map('strval', $roles) : null,
        );
        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? $path : storage_path('app/role-matrix-export.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        if ($this->wantsJson()) {
            return $this->emit(['path' => $path, 'roles' => count($payload['roles'] ?? [])]);
        }

        $this->components->info("Exported to {$path}");

        return self::SUCCESS;
    }
}
