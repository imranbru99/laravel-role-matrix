<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V3;

use Filament\Pages\Page;
use ImranDevBD\RoleMatrix\Filament\Concerns\InteractsWithMatrixPage;

final class AuditLogPage extends Page
{
    use InteractsWithMatrixPage;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static string $view = 'role-matrix::filament.pages.audit';

    public static function getSlug(): string
    {
        return (string) config('role-matrix.filament.audit_slug', 'role-matrix-audit');
    }

    public static function getNavigationLabel(): string
    {
        return __('role-matrix::ui.audit');
    }
}
