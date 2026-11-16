<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use Illuminate\Support\Str;
use ImranDevBD\RoleMatrix\Contracts\CloneServiceContract;
use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Events\RoleCloned;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Models\RolePermission;

final class CloneService implements CloneServiceContract
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly CacheService $cache,
    ) {}

    public function clone(Role $role, array $options = []): Role
    {
        $name = trim((string) ($options['name'] ?? $role->name.' copy'));
        $copy = $role->replicate(['is_super_admin', 'is_system']);
        $copy->name = $name;
        $copy->slug = $this->uniqueSlug(Str::slug($name), $role->guard_name);
        $copy->is_super_admin = false;
        $copy->is_system = false;
        $copy->is_default = false;

        if (! ($options['copy_hierarchy'] ?? false)) {
            $copy->parent_id = null;
        }

        $copy->save();

        foreach ($role->permissions as $permission) {
            $pivot = $permission->getRelation('pivot');
            $conditions = ($options['copy_conditions'] ?? true) && $pivot instanceof RolePermission ? $pivot->conditions : null;
            RolePermission::query()->create([
                'role_id' => $copy->getKey(),
                'permission_id' => $permission->getKey(),
                'conditions' => $conditions,
                'granted_at' => now(),
                'granted_by' => null,
            ]);
        }

        if ($options['copy_users'] ?? false) {
            foreach ($role->assignments as $assignment) {
                ModelRole::query()->create([
                    'role_id' => $copy->getKey(),
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                    'team_id' => $assignment->team_id,
                    'scope_key' => $assignment->scope_key,
                    'expires_at' => $assignment->expires_at,
                    'assigned_by' => $assignment->assigned_by,
                ]);
            }
        }

        $this->audit->record(AuditEvent::RoleCloned, $copy->getMorphClass(), $copy->getKey(), null, [
            'source' => $role->slug,
            'name' => $copy->name,
        ]);
        event(new RoleCloned($copy, $role));
        $this->cache->forgetAll();

        return $copy->refresh();
    }

    private function uniqueSlug(string $base, string $guard): string
    {
        $slug = $base !== '' ? $base : 'role-copy';
        $candidate = $slug;
        $i = 2;

        while (Role::query()->forGuard($guard)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }
}
