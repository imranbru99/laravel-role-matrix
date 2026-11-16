<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class RoleForm
{
    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function components(): array
    {
        $guards = array_combine(RoleMatrixConfig::guards(), RoleMatrixConfig::guards());

        return [
            TextInput::make('name')->label(__('role-matrix::ui.name'))->required()->maxLength(255)->live(onBlur: true),
            TextInput::make('slug')->label(__('role-matrix::ui.slug'))->required()->maxLength(255),
            Textarea::make('description')->label(__('role-matrix::ui.description'))->rows(3),
            ColorPicker::make('color')->label(__('role-matrix::ui.color')),
            TextInput::make('icon')->label(__('role-matrix::ui.icon')),
            Select::make('guard_name')->label(__('role-matrix::ui.guard'))->options($guards)->required(),
            Select::make('parent_id')->label(__('role-matrix::ui.parent'))->relationship('parent', 'name')->searchable()->preload(),
            Toggle::make('is_default')->label(__('role-matrix::ui.default_role')),
        ];
    }
}
