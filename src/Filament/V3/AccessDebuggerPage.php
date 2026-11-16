<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V3;

use Filament\Pages\Page;
use ImranDevBD\RoleMatrix\Filament\Concerns\InteractsWithMatrixPage;

final class AccessDebuggerPage extends Page
{
    use InteractsWithMatrixPage;

    protected static ?string $navigationIcon = 'heroicon-o-bug-ant';

    protected static string $view = 'role-matrix::filament.pages.debugger';

    public static function getSlug(): string
    {
        return (string) config('role-matrix.filament.debugger_slug', 'role-matrix-debugger');
    }

    public static function getNavigationLabel(): string
    {
        return __('role-matrix::ui.debugger');
    }
}
