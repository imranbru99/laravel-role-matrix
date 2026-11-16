<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Enums\RoleStatus;
use ImranDevBD\RoleMatrix\Exceptions\SuperAdminProtectedException;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Role;

final class SuperAdminGuard
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function assertCanUpdate(Role $role, array $attributes): void
    {
        if (! $role->is_super_admin || $this->bypassed()) {
            return;
        }

        foreach (['name', 'slug', 'guard_name', 'parent_id', 'level', 'is_system'] as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            if ((string) $role->getAttribute($key) !== (string) $attributes[$key]) {
                $this->deny("The Super Admin role cannot change [{$key}].");
            }
        }

        if (array_key_exists('is_super_admin', $attributes) && ! $attributes['is_super_admin']) {
            $this->deny('The Super Admin flag cannot be removed.');
        }

        if (array_key_exists('status', $attributes)) {
            $next = $attributes['status'] instanceof RoleStatus ? $attributes['status']->value : (string) $attributes['status'];

            if ($next !== RoleStatus::Active->value) {
                $this->deny('The Super Admin role cannot be deactivated.');
            }
        }
    }

    public function assertCanDelete(Role $role): void
    {
        if ($role->is_super_admin && ! $this->bypassed()) {
            $this->deny('The Super Admin role cannot be deleted.');
        }
    }

    /**
     * @param  list<string>  $revoke
     * @param  array<string, mixed>  $conditions
     */
    public function assertCanChangeGrants(Role $role, array $revoke, array $conditions = []): void
    {
        if (! $role->is_super_admin || $this->bypassed()) {
            return;
        }

        if ($revoke !== []) {
            $this->deny('Permissions cannot be removed from the Super Admin role.');
        }

        foreach ($conditions as $payload) {
            if (is_array($payload) && (($payload['all'] ?? []) !== [] || ($payload['any'] ?? []) !== [])) {
                $this->deny('The Super Admin role cannot be restricted with conditions.');
            }
        }
    }

    public function assertCanDetach(Role $role, Model $user): void
    {
        if (! $role->is_super_admin || $this->bypassed()) {
            return;
        }

        $superIds = Role::query()->where('is_super_admin', true)->pluck('id');

        $anotherUser = ModelRole::query()
            ->active()
            ->whereIn('role_id', $superIds)
            ->where(function ($query) use ($user): void {
                $query->where('model_type', '!=', $user->getMorphClass())
                    ->orWhere('model_id', '!=', (string) $user->getKey());
            })
            ->exists();

        $stillAssigned = ModelRole::query()
            ->active()
            ->whereIn('role_id', $superIds)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getKey())
            ->where('role_id', '!=', $role->getKey())
            ->exists();

        if (! $anotherUser && ! $stillAssigned) {
            $this->deny('The last Super Admin assignment cannot be removed.');
        }
    }

    public function bypassed(): bool
    {
        $ability = config('role-matrix.super_admin.bypass_ability');

        if (! is_string($ability) || $ability === '') {
            return false;
        }

        $user = auth()->user();

        return $user !== null && Gate::forUser($user)->check($ability);
    }

    private function deny(string $message): never
    {
        $this->audit->record(AuditEvent::SuperAdminProtected, null, null, null, ['message' => $message]);

        throw new SuperAdminProtectedException($message);
    }
}
