<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V3;

use Filament\Pages\Page;
use ImranDevBD\RoleMatrix\Filament\Concerns\InteractsWithMatrixPage;

final class ImportExportPage extends Page
{
    use InteractsWithMatrixPage;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string $view = 'role-matrix::filament.pages.import';

    public static function getSlug(): string
    {
        return (string) config('role-matrix.filament.import_slug', 'role-matrix-import');
    }

    public static function getNavigationLabel(): string
    {
        return __('role-matrix::ui.import');
    }
}
