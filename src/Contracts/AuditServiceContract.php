<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

use ImranDevBD\RoleMatrix\Enums\AuditEvent;

interface AuditServiceContract
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        AuditEvent $event,
        ?string $auditableType = null,
        int|string|null $auditableId = null,
        ?array $old = null,
        ?array $new = null,
        ?string $batch = null,
    ): void;

    public function prune(int $days): int;
}
