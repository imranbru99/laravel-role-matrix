<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Closure;

final class ClosureCondition extends Condition
{
    public function __construct(
        private readonly string $name,
        private readonly Closure $callback,
        private readonly string $title = 'Custom',
    ) {}

    public function type(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return $this->title;
    }

    public function schema(): array
    {
        return [
            ['name' => 'note', 'type' => 'text', 'label' => __('role-matrix::conditions.custom_note'), 'default' => ''],
        ];
    }

    public function summary(array $config): string
    {
        return $this->title;
    }

    public function evaluate(ConditionContext $context, array $config): bool
    {
        return (bool) ($this->callback)($context, $config);
    }
}
