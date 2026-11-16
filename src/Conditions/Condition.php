<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Contracts\ConditionContract;

abstract class Condition implements ConditionContract
{
    public function supportsQuery(): bool
    {
        return false;
    }

    public function applyToQuery(Builder $query, Model $user, array $config): void {}

    /**
     * @param  array<string, mixed>  $config
     */
    protected function string(array $config, string $key, string $default = ''): string
    {
        $value = $config[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function list(array $config, string $key): array
    {
        $value = $config[$key] ?? [];

        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
            $value,
        ), static fn (string $item): bool => $item !== ''));
    }
}
