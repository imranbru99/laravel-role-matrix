<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use ImranDevBD\RoleMatrix\Support\CidrMatcher;

final class IpRangeCondition extends Condition
{
    public function type(): string
    {
        return 'ip_range';
    }

    public function label(): string
    {
        return __('role-matrix::conditions.ip_range');
    }

    public function schema(): array
    {
        return [
            ['name' => 'cidrs', 'type' => 'text', 'label' => __('role-matrix::conditions.cidrs'), 'default' => '10.0.0.0/8'],
        ];
    }

    public function summary(array $config): string
    {
        return __('role-matrix::conditions.ip_summary', ['cidrs' => implode(', ', $this->list($config, 'cidrs'))]);
    }

    public function evaluate(ConditionContext $context, array $config): bool
    {
        $ip = isset($context->context['ip']) && is_string($context->context['ip'])
            ? $context->context['ip']
            : (app()->bound('request') ? request()->ip() : null);

        if (! is_string($ip) || $ip === '') {
            return false;
        }

        return CidrMatcher::ipInAny($ip, $this->list($config, 'cidrs'));
    }
}
