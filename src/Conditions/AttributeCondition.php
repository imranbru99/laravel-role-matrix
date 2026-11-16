<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AttributeCondition extends Condition
{
    public function type(): string
    {
        return 'attribute';
    }

    public function label(): string
    {
        return __('role-matrix::conditions.attribute');
    }

    public function schema(): array
    {
        return [
            ['name' => 'field', 'type' => 'text', 'label' => __('role-matrix::conditions.field'), 'default' => 'status'],
            ['name' => 'operator', 'type' => 'select', 'label' => __('role-matrix::conditions.operator'), 'default' => 'eq', 'options' => [
                'eq' => '=', 'neq' => '≠', 'gt' => '>', 'gte' => '≥', 'lt' => '<', 'lte' => '≤',
                'in' => 'in', 'not_in' => 'not in', 'contains' => 'contains', 'starts_with' => 'starts with',
            ]],
            ['name' => 'value_source', 'type' => 'select', 'label' => __('role-matrix::conditions.value_source'), 'default' => 'literal', 'options' => [
                'literal' => 'value', 'user' => 'user field', 'context' => 'context key',
            ]],
            ['name' => 'value', 'type' => 'text', 'label' => __('role-matrix::conditions.value'), 'default' => ''],
        ];
    }

    public function summary(array $config): string
    {
        return $this->string($config, 'field', 'field').' '.$this->string($config, 'operator', 'eq').' '.$this->string($config, 'value');
    }

    public function evaluate(ConditionContext $context, array $config): bool
    {
        if (! is_object($context->record)) {
            return false;
        }

        $left = data_get($context->record, $this->string($config, 'field', 'id'));
        $right = $this->right($context, $config);

        return $this->compare($left, $right, $this->string($config, 'operator', 'eq'));
    }

    public function supportsQuery(): bool
    {
        return true;
    }

    public function applyToQuery(Builder $query, Model $user, array $config): void
    {
        if ($this->string($config, 'value_source', 'literal') !== 'literal') {
            return;
        }

        $field = $this->string($config, 'field', 'id');
        $value = $config['value'] ?? null;
        $operator = $this->string($config, 'operator', 'eq');

        match ($operator) {
            'neq' => $query->where($field, '!=', $value),
            'gt' => $query->where($field, '>', $value),
            'gte' => $query->where($field, '>=', $value),
            'lt' => $query->where($field, '<', $value),
            'lte' => $query->where($field, '<=', $value),
            'in' => $query->whereIn($field, $this->list($config, 'value')),
            'not_in' => $query->whereNotIn($field, $this->list($config, 'value')),
            'contains' => $query->where($field, 'like', '%'.$this->string($config, 'value').'%'),
            'starts_with' => $query->where($field, 'like', $this->string($config, 'value').'%'),
            default => $query->where($field, '=', $value),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function right(ConditionContext $context, array $config): mixed
    {
        $value = $config['value'] ?? null;

        return match ($this->string($config, 'value_source', 'literal')) {
            'user' => data_get($context->user, is_string($value) ? $value : ''),
            'context' => $context->context[is_string($value) ? $value : ''] ?? null,
            default => $value,
        };
    }

    private function compare(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            'neq' => $left != $right,
            'gt' => is_numeric($left) && is_numeric($right) && (float) $left > (float) $right,
            'gte' => is_numeric($left) && is_numeric($right) && (float) $left >= (float) $right,
            'lt' => is_numeric($left) && is_numeric($right) && (float) $left < (float) $right,
            'lte' => is_numeric($left) && is_numeric($right) && (float) $left <= (float) $right,
            'in' => in_array((string) $left, is_array($right) ? array_map(strval(...), $right) : array_map('trim', explode(',', (string) $right)), true),
            'not_in' => ! in_array((string) $left, is_array($right) ? array_map(strval(...), $right) : array_map('trim', explode(',', (string) $right)), true),
            'contains' => is_string($left) && str_contains($left, (string) $right),
            'starts_with' => is_string($left) && str_starts_with($left, (string) $right),
            default => (string) $left === (string) $right,
        };
    }
}
