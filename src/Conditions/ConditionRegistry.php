<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Closure;
use ImranDevBD\RoleMatrix\Contracts\ConditionContract;
use ImranDevBD\RoleMatrix\Exceptions\RoleMatrixException;

final class ConditionRegistry
{
    /** @var array<string, ConditionContract> */
    private array $rules = [];

    private bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $configured = config('role-matrix.abac.rules', []);

        foreach (is_array($configured) ? $configured : [] as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            $rule = app($class);

            if ($rule instanceof ConditionContract) {
                $this->rules[$rule->type()] = $rule;
            }
        }
    }

    public function register(string $name, ConditionContract|string|Closure $rule, ?string $label = null): void
    {
        $name = strtolower($name);

        if ($rule instanceof Closure) {
            $this->rules[$name] = new ClosureCondition($name, $rule, $label ?? ucfirst($name));

            return;
        }

        if (is_string($rule)) {
            $resolved = app($rule);
            if (! $resolved instanceof ConditionContract) {
                throw new RoleMatrixException("Condition [{$rule}] must implement ConditionContract.");
            }
            $rule = $resolved;
        }

        $this->rules[$name] = $rule;
    }

    public function get(string $name): ?ConditionContract
    {
        $this->boot();

        return $this->rules[strtolower($name)] ?? null;
    }

    /**
     * @return array<string, ConditionContract>
     */
    public function all(): array
    {
        $this->boot();

        return $this->rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function schemas(): array
    {
        $schemas = [];

        foreach ($this->all() as $rule) {
            $schemas[] = [
                'type' => $rule->type(),
                'label' => $rule->label(),
                'fields' => $rule->schema(),
                'query' => $rule->supportsQuery(),
            ];
        }

        return $schemas;
    }
}
