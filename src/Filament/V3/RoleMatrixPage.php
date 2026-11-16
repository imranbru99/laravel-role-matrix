<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V3;

use Filament\Pages\Page;
use ImranDevBD\RoleMatrix\Filament\Concerns\InteractsWithMatrixPage;

final class RoleMatrixPage extends Page
{
    use InteractsWithMatrixPage;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'role-matrix::filament.pages.matrix';

    public static function getSlug(): string
    {
        return (string) config('role-matrix.filament.matrix_slug', 'role-matrix');
    }
}
