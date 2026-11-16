<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use ImranDevBD\RoleMatrix\Database\Factories\ModelRoleFactory;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\UsesConfiguredKey;

/**
 * @property int|string $role_id
 * @property string $model_type
 * @property string $model_id
 * @property int|string|null $team_id
 * @property string $scope_key
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int|string|null $assigned_by
 */
class ModelRole extends Model
{
    /** @use HasFactory<ModelRoleFactory> */
    use HasFactory;

    use UsesConfiguredKey;

    /** @var list<string> */
    protected $fillable = [
        'role_id',
        'model_type',
        'model_id',
        'team_id',
        'scope_key',
        'expires_at',
        'assigned_by',
    ];

    protected static function newFactory(): ModelRoleFactory
    {
        return ModelRoleFactory::new();
    }

    public function getTable(): string
    {
        return RoleMatrixConfig::table('model_has_roles');
    }

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * @param  Builder<ModelRole>  $query
     * @return Builder<ModelRole>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleMatrixConfig::roleClass(), 'role_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
