<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Conditions\ConditionContext;
use ImranDevBD\RoleMatrix\Conditions\ConditionEngine;
use ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract;
use ImranDevBD\RoleMatrix\Enums\PermissionEffect;

final class AccessibleScope
{
    public function __construct(
        private readonly PermissionResolverContract $resolver,
        private readonly ConditionEngine $conditions,
    ) {}

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, Model $user, string $permission): Builder
    {
        $effective = $this->resolver->forUser($user);

        if ($effective->superAdmin) {
            return $query;
        }

        if ($effective->isDenied($permission)) {
            return $query->whereRaw('1 = 0');
        }

        if ($effective->hasUnconditional($permission)) {
            return $query;
        }

        $grants = $effective->conditionalFor($permission, PermissionEffect::Allow->value);
        $context = new ConditionContext($user, null, $permission);
        $usable = [];

        foreach ($grants as $grant) {
            if ($this->conditions->preconditionsPass($grant['conditions'], $context)) {
                $usable[] = $grant;
            }
        }

        if ($usable === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outer) use ($usable, $user): void {
            foreach ($usable as $grant) {
                $outer->orWhere(function (Builder $inner) use ($grant, $user): void {
                    $this->conditions->apply($inner, $user, $grant['conditions']);
                });
            }
        });
    }
}
