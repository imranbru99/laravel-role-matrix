<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class OwnershipCondition extends Condition
{
    public function type(): string
    {
        return 'ownership';
    }

    public function label(): string
    {
        return __('role-matrix::conditions.ownership');
    }

    public function schema(): array
    {
        return [
            ['name' => 'owner_key', 'type' => 'text', 'label' => __('role-matrix::conditions.owner_key'), 'default' => RoleMatrixConfig::ownerKey()],
        ];
    }

    public function summary(array $config): string
    {
        return __('role-matrix::conditions.ownership_summary', ['field' => $this->string($config, 'owner_key', RoleMatrixConfig::ownerKey())]);
    }

    public function evaluate(ConditionContext $context, array $config): bool
    {
        if (! is_object($context->record)) {
            return false;
        }

        $key = $this->string($config, 'owner_key', RoleMatrixConfig::ownerKey());
        $owner = data_get($context->record, $key);

        return $owner !== null && (string) $owner === (string) $context->user->getKey();
    }

    public function supportsQuery(): bool
    {
        return true;
    }

    public function applyToQuery(Builder $query, Model $user, array $config): void
    {
        $query->where(
            $this->string($config, 'owner_key', RoleMatrixConfig::ownerKey()),
            (string) $user->getKey(),
        );
    }
}
