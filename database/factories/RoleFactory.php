<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use ImranDevBD\RoleMatrix\Enums\RoleStatus;
use ImranDevBD\RoleMatrix\Models\Role;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'guard_name' => 'web',
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'icon' => 'heroicon-o-user',
            'parent_id' => null,
            'level' => fake()->numberBetween(1, 90),
            'is_system' => false,
            'is_super_admin' => false,
            'is_default' => false,
            'status' => RoleStatus::Active,
            'meta' => null,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'level' => 100,
            'is_system' => true,
            'is_super_admin' => true,
            'color' => '#e11d48',
            'icon' => 'heroicon-o-shield-check',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RoleStatus::Inactive]);
    }
}
