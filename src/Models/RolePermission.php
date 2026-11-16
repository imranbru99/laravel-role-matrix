<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\UsesConfiguredKey;

/**
 * @property int|string $role_id
 * @property int|string $permission_id
 * @property array<string, mixed>|null $conditions
 * @property \Illuminate\Support\Carbon|null $granted_at
 * @property int|string|null $granted_by
 */
class RolePermission extends Pivot
{
    use UsesConfiguredKey;

    public $incrementing = true;

    /** @var list<string> */
    protected $fillable = [
        'role_id',
        'permission_id',
        'conditions',
        'granted_at',
        'granted_by',
    ];

    public function getTable(): string
    {
        return RoleMatrixConfig::table('role_has_permissions');
    }

    /** @var array<string, string> */
    protected $casts = [
        'conditions' => 'array',
        'granted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleMatrixConfig::roleClass(), 'role_id');
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(RoleMatrixConfig::permissionClass(), 'permission_id');
    }
}
