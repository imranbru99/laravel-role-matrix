<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use ImranDevBD\RoleMatrix\Models\Role;

final class RoleDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Role $role) {}
}
