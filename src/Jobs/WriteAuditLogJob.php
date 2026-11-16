<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ImranDevBD\RoleMatrix\Services\AuditService;

final class WriteAuditLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $row
     */
    public function __construct(public readonly array $row) {}

    public function handle(AuditService $audit): void
    {
        $audit->write($this->row);
    }
}
