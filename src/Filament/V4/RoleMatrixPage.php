<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V4;

use Filament\Pages\Page;
use ImranDevBD\RoleMatrix\Filament\Concerns\InteractsWithMatrixPage;

final class RoleMatrixPage extends Page
{
    use InteractsWithMatrixPage;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'role-matrix::filament.pages.matrix';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return (string) config('role-matrix.filament.matrix_slug', 'role-matrix');
    }
}
