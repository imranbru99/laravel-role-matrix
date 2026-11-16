<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ImranDevBD\RoleMatrix\Enums\RoleStatus;

/** @mixin \ImranDevBD\RoleMatrix\Models\Role */
final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->resource;

        if (! $role instanceof \ImranDevBD\RoleMatrix\Models\Role) {
            return [];
        }

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'slug' => $role->slug,
            'guard_name' => $role->guard_name,
            'level' => $role->level,
            'is_super_admin' => $role->is_super_admin,
            'status' => $role->status instanceof RoleStatus ? $role->status->value : $role->status,
            'permissions' => $this->whenLoaded('permissions', fn () => $role->permissions->pluck('name')->all()),
        ];
    }
}
