<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

final class DefaultRoles
{
    /**
     * Senior roles inherit junior grants. Super Admin is intentionally not a parent.
     *
     * @return list<array{name: string, slug: string, level: int, parent: string|null, color: string, icon: string, is_super_admin?: bool, is_system?: bool, description: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'level' => 20,
                'parent' => null,
                'color' => '#64748b',
                'icon' => 'heroicon-o-eye',
                'description' => 'Read-only access to day-to-day records.',
                'is_default' => true,
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'level' => 40,
                'parent' => 'viewer',
                'color' => '#0284c7',
                'icon' => 'heroicon-o-user',
                'description' => 'Creates and updates records. Inherits Viewer.',
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'level' => 60,
                'parent' => 'staff',
                'color' => '#7c3aed',
                'icon' => 'heroicon-o-user-group',
                'description' => 'Approves work and manages a team. Inherits Staff.',
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'level' => 80,
                'parent' => 'manager',
                'color' => '#d97706',
                'icon' => 'heroicon-o-cog-6-tooth',
                'description' => 'Operates the product. Inherits Manager. Not a super admin.',
            ],
            [
                'name' => RoleMatrixConfig::superAdminName(),
                'slug' => RoleMatrixConfig::superAdminSlug(),
                'level' => 100,
                'parent' => null,
                'color' => '#e11d48',
                'icon' => 'heroicon-o-shield-check',
                'is_super_admin' => true,
                'is_system' => true,
                'description' => 'Unrestricted operator. This role cannot be deleted, renamed, or stripped.',
            ],
        ];
    }
}
