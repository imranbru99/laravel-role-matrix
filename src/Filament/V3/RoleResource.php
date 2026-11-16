<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V3;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use ImranDevBD\RoleMatrix\Filament\RoleForm;
use ImranDevBD\RoleMatrix\Filament\V3\Pages\CreateRole;
use ImranDevBD\RoleMatrix\Filament\V3\Pages\EditRole;
use ImranDevBD\RoleMatrix\Filament\V3\Pages\ListRoles;
use ImranDevBD\RoleMatrix\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public static function form(Form $form): Form
    {
        return $form->schema(RoleForm::components());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('guard_name')->badge(),
                TextColumn::make('permissions_count')->counts('permissions')->label(__('role-matrix::ui.permissions')),
                IconColumn::make('is_super_admin')->boolean()->label(__('role-matrix::ui.super')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('level', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) config('role-matrix.filament.navigation_group', 'Access Control');
    }
}
