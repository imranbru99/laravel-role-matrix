<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\DTOs;

use ImranDevBD\RoleMatrix\Support\PermissionKey;
use ImranDevBD\RoleMatrix\Support\WildcardMatcher;

/**
 * Immutable grant set. Exact names are a hash map. Wildcards are a short list
 * compiled once by WildcardMatcher.
 */
final readonly class EffectivePermissions
{
    /**
     * @param  array<string, true>  $exactAllows
     * @param  list<string>  $wildcardAllows
     * @param  array<string, true>  $exactDenies
     * @param  list<string>  $wildcardDenies
     * @param  list<array{pattern: string, conditions: array<string, mixed>, source: string, role: string|null, effect: string}>  $conditional
     * @param  array<string, list<array<string, mixed>>>  $sources
     * @param  list<string>  $roleNames
     */
    public function __construct(
        public bool $superAdmin,
        public array $exactAllows,
        public array $wildcardAllows,
        public array $exactDenies,
        public array $wildcardDenies,
        public array $conditional,
        public array $roleNames = [],
        public array $sources = [],
    ) {}

    public static function empty(): self
    {
        return new self(false, [], [], [], [], []);
    }

    public function isDenied(string $permission): bool
    {
        $permission = PermissionKey::normalize($permission);

        if (isset($this->exactDenies[$permission])) {
            return true;
        }

        foreach ($this->wildcardDenies as $pattern) {
            if ($pattern === $permission || WildcardMatcher::matches($pattern, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasUnconditional(string $permission): bool
    {
        $permission = PermissionKey::normalize($permission);

        if (isset($this->exactAllows[$permission])) {
            return true;
        }

        foreach ($this->wildcardAllows as $pattern) {
            if ($pattern === $permission || WildcardMatcher::matches($pattern, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{pattern: string, conditions: array<string, mixed>, source: string, role: string|null, effect: string}>
     */
    public function conditionalFor(string $permission, ?string $effect = null): array
    {
        $permission = PermissionKey::normalize($permission);
        $matched = [];

        foreach ($this->conditional as $grant) {
            if ($effect !== null && $grant['effect'] !== $effect) {
                continue;
            }

            if (WildcardMatcher::matches($grant['pattern'], $permission) || $grant['pattern'] === $permission) {
                $matched[] = $grant;
            }
        }

        return $matched;
    }
}
