<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use ImranDevBD\RoleMatrix\Contracts\CacheServiceContract;
use ImranDevBD\RoleMatrix\DTOs\EffectivePermissions;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use Throwable;

final class CacheService implements CacheServiceContract
{
    /** @var array<string, EffectivePermissions> */
    private array $memo = [];

    public function userKey(object $user, string $guard, ?string $teamId): string
    {
        $morph = $user instanceof \Illuminate\Database\Eloquent\Model
            ? $user->getMorphClass()
            : $user::class;

        $id = method_exists($user, 'getKey') ? (string) $user->getKey() : spl_object_id($user);

        return implode(':', [
            RoleMatrixConfig::cachePrefix(),
            'v'.$this->version(),
            $morph,
            $id,
            $guard,
            $teamId ?? '-',
        ]);
    }

    public function getUser(string $key): ?EffectivePermissions
    {
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        try {
            $cached = $this->repository()->get($key);
        } catch (Throwable) {
            return null;
        }

        if ($cached instanceof EffectivePermissions) {
            return $this->memo[$key] = $cached;
        }

        return null;
    }

    public function putUser(string $key, EffectivePermissions $permissions, int $ttl): void
    {
        $this->memo[$key] = $permissions;

        try {
            $this->repository()->put($key, $permissions, max(1, $ttl));
        } catch (Throwable) {
            // A cache outage must not fail authorization.
        }
    }

    public function rememberUser(string $key, int $ttl, callable $callback): EffectivePermissions
    {
        $existing = $this->getUser($key);

        if ($existing instanceof EffectivePermissions) {
            return $existing;
        }

        /** @var EffectivePermissions $value */
        $value = $callback();
        $this->putUser($key, $value, $ttl);

        return $value;
    }

    public function forgetUser(object $user): void
    {
        $this->flushMemo();
        $this->bump();
    }

    public function forgetAll(): void
    {
        $this->flushMemo();
        $this->bump();
    }

    public function flushMemo(): void
    {
        $this->memo = [];
    }

    private function version(): int
    {
        try {
            return (int) $this->plain()->get($this->versionKey(), 1);
        } catch (Throwable) {
            return 1;
        }
    }

    private function bump(): void
    {
        try {
            $plain = $this->plain();
            $plain->forever($this->versionKey(), ((int) $plain->get($this->versionKey(), 1)) + 1);

            if ((bool) config('role-matrix.cache.tags', true) && $plain->getStore() instanceof TaggableStore) {
                $plain->tags([RoleMatrixConfig::cachePrefix()])->flush();
            }
        } catch (Throwable) {
            // Ignore. The next read rebuilds from the database when the store is back.
        }
    }

    private function versionKey(): string
    {
        return RoleMatrixConfig::cachePrefix().':version';
    }

    private function plain(): \Illuminate\Contracts\Cache\Repository
    {
        $store = RoleMatrixConfig::cacheStore();

        return $store === null ? Cache::store() : Cache::store($store);
    }

    private function repository(): \Illuminate\Contracts\Cache\Repository
    {
        $plain = $this->plain();

        if ((bool) config('role-matrix.cache.tags', true) && $plain->getStore() instanceof TaggableStore) {
            return $plain->tags([RoleMatrixConfig::cachePrefix()]);
        }

        return $plain;
    }
}
