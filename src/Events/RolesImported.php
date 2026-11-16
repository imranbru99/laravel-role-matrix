<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RolesImported
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public string $mode, public array $report) {}
}
