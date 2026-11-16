<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Database\Factories\AuditLogFactory;
use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\UsesConfiguredKey;

/**
 * @property AuditEvent|string $event
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    use UsesConfiguredKey;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'event',
        'auditable_type',
        'auditable_id',
        'actor_type',
        'actor_id',
        'old_values',
        'new_values',
        'ip',
        'user_agent',
        'batch_uuid',
        'created_at',
    ];

    protected static function newFactory(): AuditLogFactory
    {
        return AuditLogFactory::new();
    }

    public function getTable(): string
    {
        return RoleMatrixConfig::table('audit_logs');
    }

    /** @var array<string, string> */
    protected $casts = [
        'event' => AuditEvent::class,
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];
}
