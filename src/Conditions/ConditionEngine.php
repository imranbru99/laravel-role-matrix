<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Contracts\ConditionContract;

final class ConditionEngine
{
    public function __construct(private readonly ConditionRegistry $registry) {}

    /**
     * @param  array<string, mixed>|null  $conditions
     */
    public function passes(?array $conditions, ConditionContext $context): bool
    {
        if ($this->isOpen($conditions)) {
            return true;
        }

        foreach ($conditions['all'] ?? [] as $rule) {
            if (! is_array($rule) || ! $this->rulePasses($rule, $context)) {
                return false;
            }
        }

        $any = $conditions['any'] ?? [];

        if ($any === []) {
            return true;
        }

        foreach ($any as $rule) {
            if (is_array($rule) && $this->rulePasses($rule, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     */
    public function summarize(?array $conditions): string
    {
        if ($this->isOpen($conditions)) {
            return __('role-matrix::conditions.unconditional');
        }

        $parts = [];

        foreach (['all', 'any'] as $bucket) {
            foreach ($conditions[$bucket] ?? [] as $rule) {
                if (! is_array($rule)) {
                    continue;
                }

                $type = strtolower((string) ($rule['rule'] ?? ''));
                $handler = $this->registry->get($type);
                $parts[] = $handler instanceof ConditionContract
                    ? $handler->summary($rule)
                    : $type;
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     */
    public function isOpen(?array $conditions): bool
    {
        if ($conditions === null || $conditions === []) {
            return true;
        }

        $all = $conditions['all'] ?? [];
        $any = $conditions['any'] ?? [];

        return $all === [] && $any === [];
    }

    /**
     * Non-SQL rules (time, IP) are evaluated now. SQL rules are left to the query.
     *
     * @param  array<string, mixed>|null  $conditions
     */
    public function preconditionsPass(?array $conditions, ConditionContext $context): bool
    {
        if ($this->isOpen($conditions)) {
            return true;
        }

        foreach ($conditions['all'] ?? [] as $rule) {
            if (! is_array($rule)) {
                return false;
            }

            $handler = $this->registry->get((string) ($rule['rule'] ?? ''));

            if ($handler instanceof ConditionContract && ! $handler->supportsQuery() && ! $handler->evaluate($context, $rule)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>|null  $conditions
     */
    public function apply(Builder $query, Model $user, ?array $conditions): void
    {
        if ($this->isOpen($conditions)) {
            return;
        }

        foreach ($conditions['all'] ?? [] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $handler = $this->registry->get((string) ($rule['rule'] ?? ''));

            if ($handler instanceof ConditionContract && $handler->supportsQuery()) {
                $handler->applyToQuery($query, $user, $rule);
            }
        }

        $any = array_values(array_filter(
            $conditions['any'] ?? [],
            static fn (mixed $rule): bool => is_array($rule),
        ));

        if ($any === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($any, $user): void {
            foreach ($any as $rule) {
                $handler = $this->registry->get((string) ($rule['rule'] ?? ''));

                if (! $handler instanceof ConditionContract || ! $handler->supportsQuery()) {
                    continue;
                }

                $outer->orWhere(function (Builder $inner) use ($handler, $user, $rule): void {
                    $handler->applyToQuery($inner, $user, $rule);
                });
            }
        });
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function traceRule(array $rule, ConditionContext $context): array
    {
        $type = strtolower((string) ($rule['rule'] ?? ''));
        $handler = $this->registry->get($type);
        $passed = $handler instanceof ConditionContract && $handler->evaluate($context, $rule);

        return [
            'rule' => $type,
            'passed' => $passed,
            'summary' => $handler instanceof ConditionContract ? $handler->summary($rule) : $type,
            'config' => $rule,
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function rulePasses(array $rule, ConditionContext $context): bool
    {
        $handler = $this->registry->get((string) ($rule['rule'] ?? ''));

        return $handler instanceof ConditionContract && $handler->evaluate($context, $rule);
    }
}
