<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

interface AuditDriverContract
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function write(array $row): void;
}
