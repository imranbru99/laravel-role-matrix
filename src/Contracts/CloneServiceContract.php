<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

use ImranDevBD\RoleMatrix\Models\Role;

interface CloneServiceContract
{
    /**
     * @param  array{name?: string, copy_users?: bool, copy_conditions?: bool, copy_hierarchy?: bool}  $options
     */
    public function clone(Role $role, array $options = []): Role;
}
