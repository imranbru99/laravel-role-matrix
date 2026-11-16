<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Support\ActorAttributes;

final class TeamCondition extends Condition
{
    public function type(): string
    {
        return 'team';
    }

    public function label(): string
    {
        return __('role-matrix::conditions.team');
    }

    public function schema(): array
    {
        return [
            ['name' => 'team_key', 'type' => 'text', 'label' => __('role-matrix::conditions.team_key'), 'default' => 'team_id'],
            ['name' => 'with_children', 'type' => 'toggle', 'label' => __('role-matrix::conditions.with_children'), 'default' => false],
        ];
    }

    public function summary(array $config): string
    {
        return ($config['with_children'] ?? false)
            ? __('role-matrix::conditions.team_children_summary')
            : __('role-matrix::conditions.team_summary');
    }

    public function evaluate(ConditionContext $context, array $config): bool
    {
        if (! is_object($context->record)) {
            return false;
        }

        $ids = $this->ids($context->user, $config);
        $value = data_get($context->record, $this->string($config, 'team_key', 'team_id'));

        return $value !== null && in_array((string) $value, $ids, true);
    }

    public function supportsQuery(): bool
    {
        return true;
    }

    public function applyToQuery(Builder $query, Model $user, array $config): void
    {
        $ids = $this->ids($user, $config);

        $query->whereIn($this->string($config, 'team_key', 'team_id'), $ids === [] ? ['__none__'] : $ids);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function ids(Model $user, array $config): array
    {
        return ActorAttributes::ids($user, 'team_id', 'roleMatrixTeamIds', (bool) ($config['with_children'] ?? false));
    }
}
