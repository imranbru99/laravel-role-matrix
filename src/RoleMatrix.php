<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix;

use Closure;
use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Conditions\ConditionRegistry;
use ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract;
use ImranDevBD\RoleMatrix\DTOs\PermissionExplanation;
use ImranDevBD\RoleMatrix\Registry\Module;
use ImranDevBD\RoleMatrix\Registry\ModuleRegistry;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\TeamContext;

final class RoleMatrix
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly PermissionResolverContract $resolver,
        private readonly TeamContext $teams,
        private string $guard = '',
    ) {
        if ($this->guard === '') {
            $this->guard = RoleMatrixConfig::defaultGuard();
        }
    }

    public function module(string $slug, bool $overwrite = false): Module
    {
        return $this->registry->module($slug, $overwrite);
    }

    public function modules(callable $callback): void
    {
        $callback($this->registry);
    }

    public function extend(callable $callback): void
    {
        $this->registry->extend($callback);
    }

    public function forGuard(string $guard): self
    {
        $clone = clone $this;
        $clone->guard = $guard;

        return $clone;
    }

    public function guard(): string
    {
        return $this->guard;
    }

    public function setTeam(?string $teamId): void
    {
        $this->teams->set($teamId);
    }

    public function explain(Model $user, string $permission, mixed $record = null): PermissionExplanation
    {
        return $this->resolver->explain($user, $permission, $record, $this->guard);
    }

    /**
     * @param  class-string|Contracts\ConditionContract|Closure(mixed, mixed): bool  $rule
     */
    public function condition(string $name, mixed $rule, ?string $label = null): void
    {
        app(ConditionRegistry::class)->register($name, $rule, $label);
    }

    public function registry(): ModuleRegistry
    {
        return $this->registry;
    }

    public function resolver(): PermissionResolverContract
    {
        return $this->resolver;
    }
}
