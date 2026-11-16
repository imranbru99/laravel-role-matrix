<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V4;

use Filament\Pages\Page;
use ImranDevBD\RoleMatrix\Filament\Concerns\InteractsWithMatrixPage;

final class AuditLogPage extends Page
{
    use InteractsWithMatrixPage;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string $view = 'role-matrix::filament.pages.audit';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return (string) config('role-matrix.filament.audit_slug', 'role-matrix-audit');
    }

    public static function getNavigationLabel(): string
    {
        return __('role-matrix::ui.audit');
    }
}
