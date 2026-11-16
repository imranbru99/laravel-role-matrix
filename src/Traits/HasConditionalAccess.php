<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Services\AccessibleScope;

trait HasConditionalAccess
{
    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeAccessibleBy(Builder $query, Model $user, string $permission): Builder
    {
        return app(AccessibleScope::class)->apply($query, $user, $permission);
    }
}
