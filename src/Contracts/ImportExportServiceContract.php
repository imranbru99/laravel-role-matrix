<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

interface ImportExportServiceContract
{
    /**
     * @param  list<string>|null  $roleSlugs
     * @return array<string, mixed>
     */
    public function export(?string $guard = null, ?array $roleSlugs = null): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function import(array $payload, string $mode = 'merge', bool $createMissing = false): array;
}
