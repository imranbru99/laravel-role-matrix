<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class AssignRolesAction
{
    public static function make(): Action
    {
        return Action::make('assignRoles')
            ->label(__('role-matrix::ui.roles'))
            ->form([
                Select::make('roles')
                    ->label(__('role-matrix::ui.roles'))
                    ->multiple()
                    ->options(fn (): array => Role::query()->forGuard(RoleMatrixConfig::defaultGuard())->orderBy('name')->pluck('name', 'id')->all())
                    ->required(),
            ])
            ->action(function (mixed $record, array $data): void {
                if (! is_object($record) || ! method_exists($record, 'syncRoles')) {
                    return;
                }

                $roles = is_array($data['roles'] ?? null) ? $data['roles'] : [];
                $record->syncRoles(...$roles);
            });
    }
}
