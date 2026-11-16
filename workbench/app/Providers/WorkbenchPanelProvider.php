<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use ImranDevBD\RoleMatrix\Filament\RoleMatrixPlugin;

final class WorkbenchPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->plugin(
                RoleMatrixPlugin::make()
                    ->navigationGroup('Access Control')
                    ->guard('web')
                    ->enableAuditLog()
                    ->enableImportExport(),
            );
    }
}
