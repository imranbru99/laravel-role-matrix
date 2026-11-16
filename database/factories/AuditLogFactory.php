<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Models\AuditLog;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'event' => AuditEvent::RoleUpdated,
            'auditable_type' => 'role',
            'auditable_id' => (string) fake()->numberBetween(1, 50),
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '1',
            'old_values' => ['name' => 'Old'],
            'new_values' => ['name' => 'New'],
            'ip' => fake()->ipv4(),
            'user_agent' => 'PHPUnit',
            'batch_uuid' => fake()->uuid(),
            'created_at' => now(),
        ];
    }
}
