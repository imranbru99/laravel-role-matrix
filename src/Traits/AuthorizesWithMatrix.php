<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Traits;

use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract;

trait AuthorizesWithMatrix
{
    protected function matrix(Model $user, string $permission, mixed $record = null): bool
    {
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return app(PermissionResolverContract::class)->allows($user, $permission, $record);
    }
}
