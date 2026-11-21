<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\Tables;

use Filament\Tables\Filters\SelectFilter;

final class RolesFilter
{
    public static function make(string $name = 'roles'): SelectFilter
    {
        return SelectFilter::make($name)
            ->label(__('role-matrix::ui.roles'))
            ->relationship('roles', 'name')
            ->multiple()
            ->preload();
    }
}
