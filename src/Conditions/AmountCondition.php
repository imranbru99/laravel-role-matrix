<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AmountCondition extends Condition
{
    public function type(): string
    {
        return 'amount';
    }

    public function label(): string
    {
        return __('role-matrix::conditions.amount');
    }

    public function schema(): array
    {
        return [
            ['name' => 'field', 'type' => 'text', 'label' => __('role-matrix::conditions.field'), 'default' => 'total'],
            ['name' => 'operator', 'type' => 'select', 'label' => __('role-matrix::conditions.operator'), 'default' => 'lte', 'options' => [
                'lt' => '<', 'lte' => '≤', 'gt' => '>', 'gte' => '≥', 'eq' => '=', 'between' => 'between',
            ]],
            ['name' => 'value', 'type' => 'number', 'label' => __('role-matrix::conditions.limit'), 'default' => 0],
            ['name' => 'max', 'type' => 'number', 'label' => __('role-matrix::conditions.max'), 'default' => 0],
            ['name' => 'currency', 'type' => 'text', 'label' => __('role-matrix::conditions.currency'), 'default' => ''],
            ['name' => 'currency_field', 'type' => 'text', 'label' => __('role-matrix::conditions.currency_field'), 'default' => 'currency'],
        ];
    }

    public function summary(array $config): string
    {
        $operator = $this->string($config, 'operator', 'lte');
        $value = $this->string($config, 'value', '0');
        $currency = $this->string($config, 'currency');
        $label = $operator === 'between'
            ? $value.'–'.$this->string($config, 'max', '0')
            : $operator.' '.$value;

        if ($currency !== '') {
            $label .= ' '.$currency;
        }

        return __('role-matrix::conditions.amount_summary', ['limit' => $label]);
    }

    public function evaluate(ConditionContext $context, array $config): bool
    {
        if (! is_object($context->record)) {
            return false;
        }

        if (! $this->currencyMatches($context->record, $config)) {
            return false;
        }

        $left = data_get($context->record, $this->string($config, 'field', 'total'));

        if (! is_numeric($left)) {
            return false;
        }

        return $this->compare((float) $left, $config);
    }

    public function supportsQuery(): bool
    {
        return true;
    }

    public function applyToQuery(Builder $query, Model $user, array $config): void
    {
        $field = $this->string($config, 'field', 'total');
        $value = (float) $this->string($config, 'value', '0');
        $currency = $this->string($config, 'currency');

        if ($currency !== '') {
            $query->where($this->string($config, 'currency_field', 'currency'), $currency);
        }

        match ($this->string($config, 'operator', 'lte')) {
            'lt' => $query->where($field, '<', $value),
            'gt' => $query->where($field, '>', $value),
            'gte' => $query->where($field, '>=', $value),
            'eq' => $query->where($field, '=', $value),
            'between' => $query->whereBetween($field, [$value, (float) $this->string($config, 'max', '0')]),
            default => $query->where($field, '<=', $value),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function currencyMatches(object $record, array $config): bool
    {
        $currency = $this->string($config, 'currency');

        if ($currency === '') {
            return true;
        }

        return (string) data_get($record, $this->string($config, 'currency_field', 'currency')) === $currency;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function compare(float $left, array $config): bool
    {
        $value = (float) $this->string($config, 'value', '0');

        return match ($this->string($config, 'operator', 'lte')) {
            'lt' => $left < $value,
            'gt' => $left > $value,
            'gte' => $left >= $value,
            'eq' => $left === $value,
            'between' => $left >= $value && $left <= (float) $this->string($config, 'max', '0'),
            default => $left <= $value,
        };
    }
}
