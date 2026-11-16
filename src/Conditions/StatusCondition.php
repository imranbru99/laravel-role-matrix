<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class StatusCondition extends Condition
{
    public function type(): string
    {
        return 'status';
    }

    public function label(): string
    {
        return __('role-matrix::conditions.status');
    }

    public function schema(): array
    {
        return [
            ['name' => 'field', 'type' => 'text', 'label' => __('role-matrix::conditions.field'), 'default' => 'status'],
            ['name' => 'operator', 'type' => 'select', 'label' => __('role-matrix::conditions.operator'), 'default' => 'in', 'options' => ['in' => 'in', 'not_in' => 'not in']],
            ['name' => 'values', 'type' => 'text', 'label' => __('role-matrix::conditions.values'), 'default' => 'pending'],
        ];
    }

    public function summary(array $config): string
    {
        $values = implode(', ', $this->list($config, 'values'));

        return ($config['operator'] ?? 'in') === 'not_in'
            ? __('role-matrix::conditions.status_not_summary', ['values' => $values])
            : __('role-matrix::conditions.status_summary', ['values' => $values]);
    }

    public function evaluate(ConditionContext $context, array $config): bool
    {
        if (! is_object($context->record)) {
            return false;
        }

        $current = data_get($context->record, $this->string($config, 'field', 'status'));
        $values = $this->list($config, 'values');
        $matches = in_array((string) $current, $values, true);

        return ($config['operator'] ?? 'in') === 'not_in' ? ! $matches : $matches;
    }

    public function supportsQuery(): bool
    {
        return true;
    }

    public function applyToQuery(Builder $query, Model $user, array $config): void
    {
        $field = $this->string($config, 'field', 'status');
        $values = $this->list($config, 'values');

        if (($config['operator'] ?? 'in') === 'not_in') {
            $query->whereNotIn($field, $values);

            return;
        }

        $query->whereIn($field, $values === [] ? ['__none__'] : $values);
    }
}
