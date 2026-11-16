<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

use ImranDevBD\RoleMatrix\Models\Role;

interface RoleServiceContract
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Role;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Role $role, array $attributes): Role;

    public function delete(Role $role, ?Role $reassignTo = null, bool $detach = false): void;

    public function restore(Role $role): Role;

    public function forceDelete(Role $role): void;

    public function seedDefaults(string $guard): void;
}
