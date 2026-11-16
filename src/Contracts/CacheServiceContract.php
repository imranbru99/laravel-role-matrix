<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

use ImranDevBD\RoleMatrix\DTOs\EffectivePermissions;

interface CacheServiceContract
{
    public function userKey(object $user, string $guard, ?string $teamId): string;

    public function getUser(string $key): ?EffectivePermissions;

    public function putUser(string $key, EffectivePermissions $permissions, int $ttl): void;

    public function rememberUser(string $key, int $ttl, callable $callback): EffectivePermissions;

    public function forgetUser(object $user): void;

    public function forgetAll(): void;

    public function flushMemo(): void;
}
