<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\Tables;

use Filament\Tables\Columns\TextColumn;

final class RolesColumn
{
    public static function make(string $name = 'roles.name'): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('role-matrix::ui.roles'))
            ->badge()
            ->searchable();
    }
}
