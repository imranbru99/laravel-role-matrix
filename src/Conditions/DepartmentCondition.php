<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Support\ActorAttributes;

final class DepartmentCondition extends Condition
{
    public function type(): string
    {
        return 'department';
    }

    public function label(): string
    {
        return __('role-matrix::conditions.department');
    }

    public function schema(): array
    {
        return [
            ['name' => 'department_key', 'type' => 'text', 'label' => __('role-matrix::conditions.department_key'), 'default' => 'department_id'],
            ['name' => 'with_children', 'type' => 'toggle', 'label' => __('role-matrix::conditions.with_children'), 'default' => false],
        ];
    }

    public function summary(array $config): string
    {
        return ($config['with_children'] ?? false)
            ? __('role-matrix::conditions.department_children_summary')
            : __('role-matrix::conditions.department_summary');
    }

    public function evaluate(ConditionContext $context, array $config): bool
    {
        if (! is_object($context->record)) {
            return false;
        }

        $ids = ActorAttributes::ids(
            $context->user,
            'department_id',
            'roleMatrixDepartmentIds',
            (bool) ($config['with_children'] ?? false),
        );
        $value = data_get($context->record, $this->string($config, 'department_key', 'department_id'));

        return $value !== null && in_array((string) $value, $ids, true);
    }

    public function supportsQuery(): bool
    {
        return true;
    }

    public function applyToQuery(Builder $query, Model $user, array $config): void
    {
        $ids = ActorAttributes::ids(
            $user,
            'department_id',
            'roleMatrixDepartmentIds',
            (bool) ($config['with_children'] ?? false),
        );

        $query->whereIn($this->string($config, 'department_key', 'department_id'), $ids === [] ? ['__none__'] : $ids);
    }
}
