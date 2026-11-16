<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use ImranDevBD\RoleMatrix\Conditions\ConditionRegistry;
use ImranDevBD\RoleMatrix\Enums\RoleStatus;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Models\RolePermission;
use ImranDevBD\RoleMatrix\Registry\ModuleRegistry;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class MatrixBuilder
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ConditionRegistry $conditions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(string $guard): array
    {
        $this->registry->discover();
        $roles = Role::query()->forGuard($guard)->orderByDesc('level')->orderBy('name')->get();
        $roleIds = $roles->modelKeys();
        $pivot = (new RolePermission)->getTable();
        $grants = RolePermission::query()
            ->whereIn('role_id', $roleIds === [] ? [0] : $roleIds)
            ->with('permission:id,name')
            ->get()
            ->groupBy(fn (RolePermission $row): string => (string) $row->role_id);

        $counts = ModelRole::query()
            ->active()
            ->whereIn('role_id', $roleIds === [] ? [0] : $roleIds)
            ->selectRaw('role_id, count(*) as aggregate')
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id');

        $requires = $this->registry->requirements($guard);

        return [
            'guard' => $guard,
            'guards' => RoleMatrixConfig::guards(),
            'view' => (string) config('role-matrix.filament.default_view', 'columns'),
            'density' => (string) config('role-matrix.ui.density', 'comfortable'),
            'danger_user_threshold' => (int) config('role-matrix.ui.danger_user_threshold', 10),
            'dangerous' => RoleMatrixConfig::dangerousActions(),
            'virtualize_after' => (int) config('role-matrix.ui.virtualize_after', 30),
            'brand' => (string) config('role-matrix.ui.brand', '#4f46e5'),
            'requires' => $requires,
            'required_by' => $this->registry->requiredBy($requires),
            'rules' => $this->conditions->schemas(),
            'groups' => array_map(function ($group): array {
                return [
                    'name' => $group->label,
                    'modules' => array_map(fn ($module): array => $module->toArray(), $group->modules),
                ];
            }, $this->registry->grouped($guard)),
            'roles' => $roles->map(function (Role $role) use ($grants, $counts): array {
                $cells = [];

                foreach ($grants->get((string) $role->getKey(), collect()) as $grant) {
                    $name = $grant->permission?->name;

                    if (! is_string($name)) {
                        continue;
                    }

                    $cells[$name] = [
                        'on' => true,
                        'conditions' => $grant->conditions,
                    ];
                }

                return [
                    'id' => (string) $role->getKey(),
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'color' => $role->color ?: '#4f46e5',
                    'icon' => $role->icon,
                    'locked' => (bool) $role->is_super_admin,
                    'users' => (int) ($counts[(string) $role->getKey()] ?? $counts[$role->getKey()] ?? 0),
                    'status' => $role->status instanceof RoleStatus ? $role->status->value : (string) $role->status,
                    'parent' => $role->parent_id !== null ? (string) $role->parent_id : null,
                    'grants' => $cells,
                ];
            })->values()->all(),
            'i18n' => [
                'save' => __('role-matrix::ui.save'),
                'discard' => __('role-matrix::ui.discard'),
                'unsaved' => __('role-matrix::ui.unsaved'),
                'confirm_title' => __('role-matrix::ui.confirm_title'),
                'danger_confirm' => __('role-matrix::ui.danger_confirm'),
                'dependency' => __('role-matrix::ui.dependency'),
                'locked' => __('role-matrix::ui.locked'),
                'search' => __('role-matrix::ui.search'),
                'selected' => __('role-matrix::ui.only_selected'),
                'unselected' => __('role-matrix::ui.only_unselected'),
                'conditional' => __('role-matrix::ui.only_conditional'),
                'all' => __('role-matrix::ui.all'),
                'none' => __('role-matrix::ui.none'),
                'read_only' => __('role-matrix::ui.read_only'),
                'standard' => __('role-matrix::ui.standard'),
                'full' => __('role-matrix::ui.full'),
                'conditions' => __('role-matrix::ui.conditions'),
                'close' => __('role-matrix::ui.close'),
                'apply' => __('role-matrix::ui.apply'),
                'compare' => __('role-matrix::ui.compare'),
                'columns' => __('role-matrix::ui.columns'),
                'focus' => __('role-matrix::ui.focus'),
                'added' => __('role-matrix::ui.added'),
                'removed' => __('role-matrix::ui.removed'),
                'changed' => __('role-matrix::ui.changed'),
                'empty' => __('role-matrix::ui.empty'),
            ],
        ];
    }
}
