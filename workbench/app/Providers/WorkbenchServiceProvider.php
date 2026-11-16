<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Workbench\App\RoleMatrix\Catalog;

final class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->demo()) {
            return;
        }

        config([
            'role-matrix.models.user' => \Workbench\App\Models\User::class,
            'auth.providers.users.model' => \Workbench\App\Models\User::class,
        ]);
    }

    public function boot(): void
    {
        if (! $this->demo()) {
            return;
        }

        $this->app->booted(function (): void {
            Catalog::register();
        });

        if (class_exists(\Filament\PanelProvider::class)) {
            $this->app->register(WorkbenchPanelProvider::class);
        }
    }

    private function demo(): bool
    {
        return filter_var(env('ROLE_MATRIX_DEMO', false), FILTER_VALIDATE_BOOL);
    }
}
