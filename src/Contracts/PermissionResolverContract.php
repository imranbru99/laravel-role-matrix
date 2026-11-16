<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\DTOs\EffectivePermissions;
use ImranDevBD\RoleMatrix\DTOs\PermissionExplanation;

interface PermissionResolverContract
{
    public function forUser(Model $user, ?string $guard = null, ?string $teamId = null): EffectivePermissions;

    public function allows(Model $user, string $permission, mixed $record = null, ?string $guard = null): bool;

    /**
     * @return true|false|null
     */
    public function gate(Model $user, string $ability, mixed $record = null): ?bool;

    public function explain(Model $user, string $permission, mixed $record = null, ?string $guard = null): PermissionExplanation;

    public function forget(Model $user): void;
}
