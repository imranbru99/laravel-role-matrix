<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use ImranDevBD\RoleMatrix\Database\Factories\RoleFactory;
use ImranDevBD\RoleMatrix\Enums\RoleStatus;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\UsesConfiguredKey;

/**
 * @property string $name
 * @property string $slug
 * @property string $guard_name
 * @property string|null $description
 * @property string|null $color
 * @property string|null $icon
 * @property int|string|null $parent_id
 * @property int $level
 * @property bool $is_system
 * @property bool $is_super_admin
 * @property bool $is_default
 * @property RoleStatus $status
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $deleted_at
 * @property int $permissions_count
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    use SoftDeletes;
    use UsesConfiguredKey;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'guard_name',
        'description',
        'color',
        'icon',
        'parent_id',
        'level',
        'is_system',
        'is_super_admin',
        'is_default',
        'status',
        'meta',
    ];

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }

    public function getTable(): string
    {
        return RoleMatrixConfig::table('roles');
    }

    /** @var array<string, string> */
    protected $casts = [
        'level' => 'integer',
        'is_system' => 'boolean',
        'is_super_admin' => 'boolean',
        'is_default' => 'boolean',
        'meta' => 'array',
        'status' => RoleStatus::class,
    ];

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeForGuard(Builder $query, string $guard): Builder
    {
        return $query->where($this->qualifyColumn('guard_name'), $guard);
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), RoleStatus::Active->value);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Permission, $this, RolePermission>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            RoleMatrixConfig::permissionClass(),
            RoleMatrixConfig::table('role_has_permissions'),
            'role_id',
            'permission_id',
        )->using(RoleMatrixConfig::rolePermissionClass())
            ->withPivot(['id', 'conditions', 'granted_at', 'granted_by'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ModelRole, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleMatrixConfig::modelRoleClass(), 'role_id');
    }

    /**
     * @return MorphToMany<Model, $this>
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            RoleMatrixConfig::model('user'),
            'model',
            RoleMatrixConfig::table('model_has_roles'),
            'role_id',
            'model_id',
        )->withPivot(['team_id', 'scope_key', 'expires_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function activeAssignmentCount(): int
    {
        return $this->assignments()
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    /**
     * @return Collection<int, Role>
     */
    public function ancestors(): Collection
    {
        /** @var Collection<int, Role> $all */
        $all = static::query()->forGuard($this->guard_name)->get(['id', 'parent_id', 'name', 'slug', 'is_super_admin']);
        $byId = $all->keyBy(fn (Role $role): string => (string) $role->getKey());
        $chain = new Collection;
        $current = $this->parent_id !== null ? $byId->get((string) $this->parent_id) : null;
        $guard = 0;

        while ($current instanceof self && $guard < RoleMatrixConfig::maxDepth()) {
            $chain->push($current);
            $current = $current->parent_id !== null ? $byId->get((string) $current->parent_id) : null;
            $guard++;
        }

        return $chain;
    }
}
