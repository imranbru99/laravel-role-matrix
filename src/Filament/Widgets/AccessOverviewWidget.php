<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\Widgets;

use Filament\Widgets\Widget;
use ImranDevBD\RoleMatrix\Models\Role;

final class AccessOverviewWidget extends Widget
{
    protected static string $view = 'role-matrix::filament.widgets.overview';

    protected static ?int $sort = 2;

    /**
     * @return array<string, int>
     */
    protected function getViewData(): array
    {
        return [
            'roles' => Role::query()->count(),
            'superAdmins' => Role::query()->where('is_super_admin', true)->count(),
        ];
    }
}
