<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

use ImranDevBD\RoleMatrix\DTOs\SyncReport;

interface SyncServiceContract
{
    public function sync(?string $guard = null, bool $prune = false, bool $dryRun = false): SyncReport;
}
