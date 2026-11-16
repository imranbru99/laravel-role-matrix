<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use ImranDevBD\RoleMatrix\Database\Factories\ModelPermissionFactory;
use ImranDevBD\RoleMatrix\Enums\PermissionEffect;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\UsesConfiguredKey;

/**
 * @property int|string $permission_id
 * @property string $model_type
 * @property string $model_id
 * @property PermissionEffect $effect
 * @property array<string, mixed>|null $conditions
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int|string|null $team_id
 * @property string $scope_key
 */
class ModelPermission extends Model
{
    /** @use HasFactory<ModelPermissionFactory> */
    use HasFactory;

    use UsesConfiguredKey;

    /** @var list<string> */
    protected $fillable = [
        'permission_id',
        'model_type',
        'model_id',
        'effect',
        'conditions',
        'expires_at',
        'team_id',
        'scope_key',
        'assigned_by',
    ];

    protected static function newFactory(): ModelPermissionFactory
    {
        return ModelPermissionFactory::new();
    }

    public function getTable(): string
    {
        return RoleMatrixConfig::table('model_has_permissions');
    }

    /** @var array<string, string> */
    protected $casts = [
        'effect' => PermissionEffect::class,
        'conditions' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * @param  Builder<ModelPermission>  $query
     * @return Builder<ModelPermission>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(RoleMatrixConfig::permissionClass(), 'permission_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
