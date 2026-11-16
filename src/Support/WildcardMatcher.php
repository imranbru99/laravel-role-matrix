<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

/**
 * Wildcard permissions are compiled to a regular expression once per pattern.
 * The cache is pure (pattern in, regex out) and safe to keep on the worker.
 */
final class WildcardMatcher
{
    /** @var array<string, string> */
    private static array $compiled = [];

    private static ?bool $enabled = null;

    public static function setEnabled(?bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    public static function matches(string $pattern, string $permission): bool
    {
        $pattern = strtolower(trim($pattern));
        $permission = strtolower(trim($permission));

        if ($pattern === '' || $permission === '') {
            return false;
        }

        if (! self::enabled() && str_contains($pattern, '*')) {
            return false;
        }

        if ($pattern === '*') {
            return true;
        }

        if (! str_contains($pattern, '*')) {
            return $pattern === $permission;
        }

        if (! isset(self::$compiled[$pattern])) {
            self::$compiled[$pattern] = self::compile($pattern);
        }

        return preg_match(self::$compiled[$pattern], $permission) === 1;
    }

    public static function flush(): void
    {
        self::$compiled = [];
        self::$enabled = null;
    }

    private static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        if (! function_exists('app')) {
            return true;
        }

        try {
            if (! app()->bound('config')) {
                return true;
            }
        } catch (\Throwable) {
            return true;
        }

        return (bool) config('role-matrix.wildcards.enabled', true);
    }

    private static function compile(string $pattern): string
    {
        $parts = explode('.', $pattern);
        $segments = array_map(static function (string $segment): string {
            if ($segment === '*') {
                return '[a-z0-9][a-z0-9_-]*';
            }

            $quoted = preg_quote($segment, '/');

            return str_replace('\*', '[a-z0-9_-]*', $quoted);
        }, $parts);

        return '/^'.implode('\.', $segments).'$/';
    }
}
