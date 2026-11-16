<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

use BackedEnum;
use ImranDevBD\RoleMatrix\Exceptions\InvalidModuleException;

final class PermissionKey
{
    public static function make(string $module, string $action): string
    {
        return strtolower($module).'.'.strtolower($action);
    }

    public static function normalize(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidModuleException('A permission must be a non-empty string, integer id, or backed enum.');
        }

        return strtolower(trim($value));
    }

    public static function isWildcard(string $key): bool
    {
        return str_contains($key, '*');
    }

    public static function isConcrete(string $key): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9_-]*\.[a-z0-9][a-z0-9_-]*$/', strtolower($key));
    }

    public static function isPattern(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, ['*', '*.*'], true)) {
            return true;
        }

        return (bool) preg_match('/^(\*|[a-z0-9][a-z0-9_-]*)\.(\*|[a-z0-9][a-z0-9_-]*)$/', $key);
    }

    public static function module(string $key): string
    {
        return explode('.', strtolower($key), 2)[0];
    }

    public static function action(string $key): string
    {
        $parts = explode('.', strtolower($key), 2);

        return $parts[1] ?? '';
    }

    /**
     * Every stored pattern that could grant this concrete permission.
     *
     * @return list<string>
     */
    public static function covering(string $permission): array
    {
        $permission = self::normalize($permission);

        if (! self::isConcrete($permission)) {
            return array_values(array_unique([$permission, '*', '*.*']));
        }

        return [
            $permission,
            '*',
            '*.*',
            self::module($permission).'.*',
            '*.'.self::action($permission),
        ];
    }

    public static function assertModule(string $slug): void
    {
        $slug = strtolower($slug);

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $slug)) {
            throw new InvalidModuleException("Invalid module slug [{$slug}]. Use a lowercase slug such as inventory or sales-orders.");
        }
    }

    public static function assertAction(string $action): void
    {
        $action = strtolower($action);

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $action)) {
            throw new InvalidModuleException("Invalid action name [{$action}]. Use a lowercase slug such as view, create, or approve.");
        }
    }
}
