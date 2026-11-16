<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ImranDevBD\RoleMatrix\Database\Factories\PermissionFactory;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\UsesConfiguredKey;

/**
 * @property string $name
 * @property string $guard_name
 * @property string|null $module_slug
 * @property string|null $action
 * @property string|null $group
 * @property string|null $description
 * @property bool $is_system
 * @property array<string, mixed>|null $meta
 */
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    use UsesConfiguredKey;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'guard_name',
        'module_slug',
        'action',
        'group',
        'description',
        'is_system',
        'meta',
    ];

    protected static function newFactory(): PermissionFactory
    {
        return PermissionFactory::new();
    }

    public function getTable(): string
    {
        return RoleMatrixConfig::table('permissions');
    }

    /** @var array<string, string> */
    protected $casts = [
        'is_system' => 'boolean',
        'meta' => 'array',
    ];

    /**
     * @param  Builder<Permission>  $query
     * @return Builder<Permission>
     */
    public function scopeForGuard(Builder $query, string $guard): Builder
    {
        return $query->where($this->qualifyColumn('guard_name'), $guard);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            RoleMatrixConfig::roleClass(),
            RoleMatrixConfig::table('role_has_permissions'),
            'permission_id',
            'role_id',
        )->withPivot(['conditions', 'granted_at', 'granted_by'])
            ->withTimestamps();
    }
}
