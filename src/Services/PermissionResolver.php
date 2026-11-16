<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use ImranDevBD\RoleMatrix\Conditions\ConditionContext;
use ImranDevBD\RoleMatrix\Conditions\ConditionEngine;
use ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract;
use ImranDevBD\RoleMatrix\DTOs\EffectivePermissions;
use ImranDevBD\RoleMatrix\DTOs\PermissionExplanation;
use ImranDevBD\RoleMatrix\Enums\PermissionEffect;
use ImranDevBD\RoleMatrix\Enums\RoleStatus;
use ImranDevBD\RoleMatrix\Models\ModelPermission;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Models\RolePermission;
use ImranDevBD\RoleMatrix\Support\HierarchyWalker;
use ImranDevBD\RoleMatrix\Support\PermissionKey;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\TeamContext;

final class PermissionResolver implements PermissionResolverContract
{
    public function __construct(
        private readonly CacheService $cache,
        private readonly ConditionEngine $conditions,
        private readonly TeamContext $teams,
    ) {}

    public function forUser(Model $user, ?string $guard = null, ?string $teamId = null): EffectivePermissions
    {
        $guard ??= $this->guardFor($user);
        $teamId = RoleMatrixConfig::teamsEnabled() ? ($teamId ?? $this->teams->current()) : null;
        $key = $this->cache->userKey($user, $guard, $teamId);
        $cached = $this->cache->getUser($key);

        if ($cached instanceof EffectivePermissions) {
            return $cached;
        }

        [$effective, $ttl] = $this->compute($user, $guard, $teamId);
        $this->cache->putUser($key, $effective, $ttl);

        return $effective;
    }

    public function allows(Model $user, string $permission, mixed $record = null, ?string $guard = null): bool
    {
        return $this->evaluate($user, PermissionKey::normalize($permission), $record, $guard)['allowed'];
    }

    public function gate(Model $user, string $ability, mixed $record = null): ?bool
    {
        if (! (bool) config('role-matrix.gate.enabled', true)) {
            return null;
        }

        $super = $this->forUser($user)->superAdmin;

        if ($super) {
            $intercept = (string) config('role-matrix.super_admin.intercept', 'all');

            if ($intercept === 'all' || str_contains($ability, '.') || $ability === '*') {
                return true;
            }

            return null;
        }

        if (! str_contains($ability, '.') && $ability !== '*') {
            return null;
        }

        if (! PermissionKey::isPattern($ability) && ! PermissionKey::isConcrete($ability)) {
            return null;
        }

        $result = $this->evaluate($user, PermissionKey::normalize($ability), $record);

        if ($result['reason'] === 'direct_deny') {
            return false;
        }

        return $result['allowed'] ? true : null;
    }

    public function explain(Model $user, string $permission, mixed $record = null, ?string $guard = null): PermissionExplanation
    {
        $permission = PermissionKey::normalize($permission);
        $result = $this->evaluate($user, $permission, $record, $guard, true);

        return new PermissionExplanation(
            allowed: $result['allowed'],
            permission: $permission,
            reason: $result['reason'],
            trace: $result['trace'],
            superAdmin: $result['super'],
        );
    }

    public function forget(Model $user): void
    {
        $this->cache->forgetUser($user);
    }

    /**
     * @return array{allowed: bool, reason: string, trace: list<array<string, mixed>>, super: bool}
     */
    private function evaluate(Model $user, string $permission, mixed $record, ?string $guard = null, bool $trace = false): array
    {
        $effective = $this->forUser($user, $guard);
        $steps = [];
        $record = is_object($record) ? $record : null;

        if ($effective->superAdmin) {
            return [
                'allowed' => true,
                'reason' => 'super_admin',
                'super' => true,
                'trace' => $trace ? [['step' => 'super_admin', 'passed' => true, 'detail' => 'Super Admin short-circuit']] : [],
            ];
        }

        if ($effective->isDenied($permission)) {
            return $this->decision(false, 'direct_deny', true, $trace, 'A direct deny matches this permission.', $steps);
        }

        if (RoleMatrixConfig::abacEnabled()) {
            foreach ($effective->conditionalFor($permission, PermissionEffect::Deny->value) as $grant) {
                $ruleTrace = $this->ruleTrace($grant['conditions'], $user, $record, $permission);
                $passed = $this->grantPasses($grant['conditions'], $user, $record, $permission);

                if ($trace) {
                    $steps[] = ['step' => 'conditional_deny', 'passed' => $passed, 'source' => $grant['source'], 'rules' => $ruleTrace];
                }

                if ($passed) {
                    return $this->decision(false, 'direct_deny', false, $trace, 'A conditional deny matched.', $steps);
                }
            }
        }

        if ($effective->hasUnconditional($permission)) {
            if ($trace) {
                $steps[] = [
                    'step' => 'allow',
                    'passed' => true,
                    'sources' => $effective->sources[$permission] ?? [],
                    'detail' => 'Unconditional grant',
                ];
            }

            return $this->decision(true, 'allow', false, $trace, 'Unconditional grant', $steps);
        }

        $grants = $effective->conditionalFor($permission, PermissionEffect::Allow->value);

        if ($grants === []) {
            return $this->decision(false, 'none', false, $trace, 'No matching grant.', $steps);
        }

        if ($record === null && ! RoleMatrixConfig::abacStrict()) {
            return $this->decision(true, 'conditional_class', false, $trace, 'Conditional grant allows class-level checks.', $steps);
        }

        if ($record === null) {
            return $this->decision(false, 'strict', false, $trace, 'Strict ABAC requires a record.', $steps);
        }

        foreach ($grants as $grant) {
            $passed = $this->grantPasses($grant['conditions'], $user, $record, $permission);

            if ($trace) {
                $steps[] = [
                    'step' => 'conditional_allow',
                    'passed' => $passed,
                    'source' => $grant['source'],
                    'role' => $grant['role'],
                    'rules' => $this->ruleTrace($grant['conditions'], $user, $record, $permission),
                ];
            }

            if ($passed) {
                return $this->decision(true, $grant['source'] === 'direct' ? 'direct_allow' : 'role', false, $trace, 'Conditional grant passed.', $steps);
            }
        }

        return $this->decision(false, 'conditions_failed', false, $trace, 'Every conditional grant failed.', $steps);
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array{allowed: bool, reason: string, trace: list<array<string, mixed>>, super: bool}
     */
    private function decision(bool $allowed, string $reason, bool $super, bool $trace, string $detail, array $steps): array
    {
        if ($trace) {
            $steps[] = ['step' => 'decision', 'passed' => $allowed, 'reason' => $reason, 'detail' => $detail];
        }

        return ['allowed' => $allowed, 'reason' => $reason, 'super' => $super, 'trace' => $trace ? $steps : []];
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     */
    private function grantPasses(?array $conditions, Model $user, mixed $record, string $permission): bool
    {
        if (! RoleMatrixConfig::abacEnabled() || $this->conditions->isOpen($conditions)) {
            return true;
        }

        return $this->conditions->passes($conditions, new ConditionContext($user, $record, $permission));
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     * @return list<array<string, mixed>>
     */
    private function ruleTrace(?array $conditions, Model $user, mixed $record, string $permission): array
    {
        if ($conditions === null) {
            return [];
        }

        $context = new ConditionContext($user, is_object($record) ? $record : null, $permission);
        $rows = [];

        foreach (['all', 'any'] as $bucket) {
            foreach ($conditions[$bucket] ?? [] as $rule) {
                if (is_array($rule)) {
                    $rows[] = ['bucket' => $bucket] + $this->conditions->traceRule($rule, $context);
                }
            }
        }

        return $rows;
    }

    /**
     * @return array{0: EffectivePermissions, 1: int}
     */
    private function compute(Model $user, string $guard, ?string $teamId): array
    {
        $assignments = $this->assignments($user, $teamId);
        $expiresAt = $this->soonest($assignments->pluck('expires_at')->all());

        /** @var \Illuminate\Database\Eloquent\Collection<int, Role> $roles */
        $roles = Role::query()
            ->forGuard($guard)
            ->get(['id', 'parent_id', 'name', 'slug', 'status', 'is_super_admin', 'guard_name']);

        $byId = [];
        $parents = [];

        foreach ($roles as $role) {
            $id = (string) $role->getKey();
            $byId[$id] = $role;
            $parents[$id] = $role->parent_id !== null ? (string) $role->parent_id : null;
        }

        $directRoleIds = [];

        foreach ($assignments as $assignment) {
            $roleId = (string) $assignment->role_id;
            $role = $byId[$roleId] ?? null;

            if (! $role instanceof Role || $role->status !== RoleStatus::Active) {
                continue;
            }

            $directRoleIds[$roleId] = true;
        }

        if ($this->containsSuperAdmin($directRoleIds, $byId)) {
            return [new EffectivePermissions(true, [], [], [], [], [], array_values(array_map(
                fn (string $id): string => (string) $byId[$id]->name,
                array_keys($directRoleIds),
            ))), $this->ttl($expiresAt)];
        }

        $resolved = $directRoleIds;

        if (RoleMatrixConfig::hierarchyEnabled()) {
            foreach (array_keys($directRoleIds) as $roleId) {
                foreach (HierarchyWalker::ancestors((string) $roleId, $parents, RoleMatrixConfig::maxDepth()) as $ancestorId) {
                    $ancestor = $byId[$ancestorId] ?? null;

                    if (! $ancestor instanceof Role || $ancestor->is_super_admin) {
                        break;
                    }

                    if ($ancestor->status !== RoleStatus::Active) {
                        continue;
                    }

                    $resolved[$ancestorId] = true;
                }
            }
        }

        $exactAllows = [];
        $wildcardAllows = [];
        $conditional = [];
        $sources = [];
        $roleNames = [];

        if ($resolved !== []) {
            $pivot = (new RolePermission)->getTable();
            $permissionTable = (new Permission)->getTable();
            $grants = RolePermission::query()
                ->whereIn($pivot.'.role_id', array_keys($resolved))
                ->join($permissionTable.' as rm_permissions', 'rm_permissions.id', '=', $pivot.'.permission_id')
                ->where('rm_permissions.guard_name', $guard)
                ->toBase()
                ->get([
                    $pivot.'.role_id as role_id',
                    $pivot.'.conditions as conditions',
                    'rm_permissions.name as permission_name',
                ]);

            foreach ($grants as $grant) {
                $roleId = (string) $grant->role_id;
                $name = strtolower((string) $grant->permission_name);
                $role = $byId[$roleId] ?? null;
                $roleName = $role instanceof Role ? (string) $role->name : null;
                $inherited = ! isset($directRoleIds[$roleId]);
                $source = [
                    'source' => $inherited ? 'inherited' : 'role',
                    'role' => $roleName,
                    'role_id' => $roleId,
                ];

                if ($roleName !== null) {
                    $roleNames[$roleName] = true;
                }

                $this->placeGrant(
                    $name,
                    $this->decode($grant->conditions),
                    $source,
                    PermissionEffect::Allow->value,
                    $exactAllows,
                    $wildcardAllows,
                    $conditional,
                    $sources,
                );
            }
        }

        if (RoleMatrixConfig::directPermissionsEnabled()) {
            $directExpires = $this->directGrants(
                $user,
                $guard,
                $teamId,
                $exactAllows,
                $wildcardAllows,
                $conditional,
                $sources,
            );
            $expiresAt = $this->soonest([$expiresAt, $directExpires]);
        }

        $denies = $this->denies($sources);

        return [new EffectivePermissions(
            superAdmin: false,
            exactAllows: $exactAllows,
            wildcardAllows: array_values(array_unique($wildcardAllows)),
            exactDenies: $denies['exact'],
            wildcardDenies: $denies['wild'],
            conditional: $conditional,
            roleNames: array_keys($roleNames),
            sources: $sources,
        ), $this->ttl($expiresAt)];
    }

    /**
     * @param  array<string, true>  $roleIds
     * @param  array<string, Role>  $byId
     */
    private function containsSuperAdmin(array $roleIds, array $byId): bool
    {
        foreach (array_keys($roleIds) as $roleId) {
            $role = $byId[$roleId] ?? null;

            if ($role instanceof Role && $role->is_super_admin) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, true>  $exactAllows
     * @param  list<string>  $wildcardAllows
     * @param  list<array{pattern: string, conditions: array<string, mixed>, source: string, role: string|null, effect: string}>  $conditional
     * @param  array<string, list<array<string, mixed>>>  $sources
     */
    private function directGrants(
        Model $user,
        string $guard,
        ?string $teamId,
        array &$exactAllows,
        array &$wildcardAllows,
        array &$conditional,
        array &$sources,
    ): ?Carbon {
        $table = (new ModelPermission)->getTable();
        $permissionTable = (new Permission)->getTable();

        $query = ModelPermission::query()
            ->where($table.'.model_type', $user->getMorphClass())
            ->where($table.'.model_id', (string) $user->getKey())
            ->where(function ($inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if (RoleMatrixConfig::teamsEnabled()) {
            $query->where(function ($inner) use ($teamId): void {
                $inner->where('scope_key', '');

                if ($teamId !== null) {
                    $inner->orWhere('scope_key', $teamId);
                }
            });
        }

        $rows = $query
            ->join($permissionTable.' as rm_permissions', 'rm_permissions.id', '=', $table.'.permission_id')
            ->where('rm_permissions.guard_name', $guard)
            ->toBase()
            ->get([
                $table.'.effect as effect',
                $table.'.conditions as conditions',
                $table.'.expires_at as expires_at',
                'rm_permissions.name as permission_name',
            ]);

        $soonest = null;

        foreach ($rows as $row) {
            $soonest = $this->soonest([$soonest, $row->expires_at]);
            $effect = (string) $row->effect;
            $this->placeGrant(
                strtolower((string) $row->permission_name),
                $this->decode($row->conditions),
                ['source' => 'direct', 'role' => null, 'effect' => $effect],
                $effect,
                $exactAllows,
                $wildcardAllows,
                $conditional,
                $sources,
                $effect === PermissionEffect::Deny->value,
            );
        }

        return $soonest;
    }

    /**
     * @param  array<string, true>  $exactAllows
     * @param  list<string>  $wildcardAllows
     * @param  list<array{pattern: string, conditions: array<string, mixed>, source: string, role: string|null, effect: string}>  $conditional
     * @param  array<string, list<array<string, mixed>>>  $sources
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>|null  $conditions
     */
    private function placeGrant(
        string $name,
        ?array $conditions,
        array $source,
        string $effect,
        array &$exactAllows,
        array &$wildcardAllows,
        array &$conditional,
        array &$sources,
        bool $deny = false,
    ): void {
        $open = $this->conditions->isOpen($conditions);
        $sources[$name] ??= [];
        $sources[$name][] = $source + ['conditions' => ! $open];

        if (! $open) {
            $conditional[] = [
                'pattern' => $name,
                'conditions' => $conditions ?? [],
                'source' => (string) ($source['source'] ?? 'role'),
                'role' => isset($source['role']) && is_string($source['role']) ? $source['role'] : null,
                'effect' => $effect,
            ];

            return;
        }

        if ($deny) {
            return;
        }

        if (PermissionKey::isWildcard($name)) {
            $wildcardAllows[] = $name;

            return;
        }

        $exactAllows[$name] = true;
    }

    /**
     * Direct denies are tracked on the source list and materialized here so a
     * deny never becomes an allow. Conditional denies stay in $conditional.
     *
     * @param  array<string, list<array<string, mixed>>>  $sources
     * @return array{exact: array<string, true>, wild: list<string>}
     */
    private function denies(array $sources): array
    {
        $exact = [];
        $wild = [];

        foreach ($sources as $name => $entries) {
            foreach ($entries as $entry) {
                if (($entry['effect'] ?? PermissionEffect::Allow->value) !== PermissionEffect::Deny->value) {
                    continue;
                }

                if (($entry['conditions'] ?? false) === true) {
                    continue;
                }

                if (PermissionKey::isWildcard($name)) {
                    $wild[] = $name;
                } else {
                    $exact[$name] = true;
                }
            }
        }

        return ['exact' => $exact, 'wild' => array_values(array_unique($wild))];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ModelRole>
     */
    private function assignments(Model $user, ?string $teamId): \Illuminate\Database\Eloquent\Collection
    {
        $query = ModelRole::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getKey())
            ->where(function ($inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if (RoleMatrixConfig::teamsEnabled()) {
            $query->where(function ($inner) use ($teamId): void {
                $inner->where('scope_key', '');

                if ($teamId !== null) {
                    $inner->orWhere('scope_key', $teamId);
                }
            });
        }

        return $query->get(['id', 'role_id', 'expires_at', 'scope_key']);
    }

    private function guardFor(Model $user): string
    {
        $guard = $user->getAttribute('guard_name');

        if (is_string($guard) && $guard !== '') {
            return $guard;
        }

        if (method_exists($user, 'roleMatrixGuard')) {
            return (string) $user->roleMatrixGuard();
        }

        return RoleMatrixConfig::defaultGuard();
    }

    /**
     * @return array<mixed>|null
     */
    private function decode(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function soonest(array $values): ?Carbon
    {
        $soonest = null;

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $moment = $value instanceof Carbon ? $value : Carbon::parse((string) $value);

            if ($moment->isPast()) {
                continue;
            }

            if ($soonest === null || $moment->lt($soonest)) {
                $soonest = $moment;
            }
        }

        return $soonest;
    }

    private function ttl(?Carbon $expiresAt): int
    {
        $ttl = RoleMatrixConfig::cacheTtl();

        if ($expiresAt === null) {
            return $ttl;
        }

        return max(1, min($ttl, (int) abs($expiresAt->getTimestamp() - now()->getTimestamp())));
    }
}
