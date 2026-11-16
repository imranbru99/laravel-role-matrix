<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ImranDevBD\RoleMatrix\Enums\PermissionEffect;
use ImranDevBD\RoleMatrix\Models\ModelPermission;
use ImranDevBD\RoleMatrix\Models\Permission;

/**
 * @extends Factory<ModelPermission>
 */
class ModelPermissionFactory extends Factory
{
    protected $model = ModelPermission::class;

    public function definition(): array
    {
        return [
            'permission_id' => Permission::factory(),
            'model_type' => 'App\\Models\\User',
            'model_id' => (string) fake()->numberBetween(1, 1000),
            'effect' => PermissionEffect::Allow,
            'conditions' => null,
            'expires_at' => null,
            'team_id' => null,
            'scope_key' => '',
            'assigned_by' => null,
        ];
    }

    public function deny(): static
    {
        return $this->state(fn (): array => ['effect' => PermissionEffect::Deny]);
    }
}
