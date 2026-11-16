<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Conditions\ConditionContext;

interface ConditionContract
{
    public function type(): string;

    public function label(): string;

    /**
     * Fields the matrix slide-over renders for this rule.
     *
     * @return list<array{name: string, type: string, label: string, default?: mixed, options?: array<string, string>}>
     */
    public function schema(): array;

    /**
     * @param  array<string, mixed>  $config
     */
    public function summary(array $config): string;

    /**
     * @param  array<string, mixed>  $config
     */
    public function evaluate(ConditionContext $context, array $config): bool;

    public function supportsQuery(): bool;

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $config
     */
    public function applyToQuery(Builder $query, Model $user, array $config): void;
}
