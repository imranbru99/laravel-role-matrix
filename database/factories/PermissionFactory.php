<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Support\PermissionKey;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $module = fake()->unique()->lexify('module????');
        $action = fake()->randomElement(['view', 'create', 'update', 'delete', 'export']);

        return [
            'name' => PermissionKey::make($module, $action),
            'guard_name' => 'web',
            'module_slug' => $module,
            'action' => $action,
            'group' => 'General',
            'description' => null,
            'is_system' => false,
            'meta' => ['source' => 'factory'],
        ];
    }
}
