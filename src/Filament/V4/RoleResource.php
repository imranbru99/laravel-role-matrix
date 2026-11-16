<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V4;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use ImranDevBD\RoleMatrix\Filament\RoleForm;
use ImranDevBD\RoleMatrix\Filament\V4\Pages\CreateRole;
use ImranDevBD\RoleMatrix\Filament\V4\Pages\EditRole;
use ImranDevBD\RoleMatrix\Filament\V4\Pages\ListRoles;
use ImranDevBD\RoleMatrix\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(RoleForm::components());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('guard_name')->badge(),
                TextColumn::make('permissions_count')->counts('permissions'),
                IconColumn::make('is_super_admin')->boolean(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
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
}
