<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ModulesSynced
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public string $guard, public array $report) {}
}
