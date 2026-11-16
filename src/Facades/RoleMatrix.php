<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ImranDevBD\RoleMatrix\Registry\Module module(string $slug, bool $overwrite = false)
 * @method static void modules(callable $callback)
 * @method static void extend(callable $callback)
 * @method static \ImranDevBD\RoleMatrix\RoleMatrix forGuard(string $guard)
 * @method static string guard()
 * @method static void setTeam(?string $teamId)
 * @method static \ImranDevBD\RoleMatrix\DTOs\PermissionExplanation explain(\Illuminate\Database\Eloquent\Model $user, string $permission, mixed $record = null)
 * @method static void condition(string $name, mixed $rule, ?string $label = null)
 * @method static \ImranDevBD\RoleMatrix\Registry\ModuleRegistry registry()
 * @method static \ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract resolver()
 *
 * @see \ImranDevBD\RoleMatrix\RoleMatrix
 */
final class RoleMatrix extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ImranDevBD\RoleMatrix\RoleMatrix::class;
    }
}
