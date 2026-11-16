<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Role;

/**
 * @extends Factory<ModelRole>
 */
class ModelRoleFactory extends Factory
{
    protected $model = ModelRole::class;

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'model_type' => 'App\\Models\\User',
            'model_id' => (string) fake()->numberBetween(1, 1000),
            'team_id' => null,
            'scope_key' => '',
            'expires_at' => null,
            'assigned_by' => null,
        ];
    }
}
