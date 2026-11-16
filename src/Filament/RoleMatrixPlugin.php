<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use ImranDevBD\RoleMatrix\Filament\Support\FilamentVersionAdapter;

final class RoleMatrixPlugin implements Plugin
{
    /** @var class-string|null */
    protected ?string $roleResource = null;

    protected string $guard = '';

    protected ?string $navigationGroup = null;

    protected bool $audit = true;

    protected bool $import = true;

    protected bool $debugger = true;

    protected ?Closure $canAccess = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'role-matrix';
    }

    public function guard(string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    /**
     * @param  class-string  $resource
     */
    public function roleResource(string $resource): static
    {
        $this->roleResource = $resource;

        return $this;
    }

    public function enableAuditLog(bool $enabled = true): static
    {
        $this->audit = $enabled;

        return $this;
    }

    public function enableImportExport(bool $enabled = true): static
    {
        $this->import = $enabled;

        return $this;
    }

    public function canAccess(Closure $callback): static
    {
        $this->canAccess = $callback;

        return $this;
    }

    public function configuredGuard(): string
    {
        return $this->guard;
    }

    public function allows(mixed $user): bool
    {
        if (! $this->canAccess instanceof Closure) {
            return true;
        }

        return (bool) ($this->canAccess)($user);
    }

    public function register(Panel $panel): void
    {
        if ($this->navigationGroup !== null) {
            config(['role-matrix.filament.navigation_group' => $this->navigationGroup]);
        }

        if ($this->guard !== '') {
            config(['role-matrix.guards.default' => $this->guard]);
        }

        $pages = [FilamentVersionAdapter::matrixPage()];

        if ($this->audit && (bool) config('role-matrix.filament.enable_audit', true)) {
            $pages[] = FilamentVersionAdapter::auditPage();
        }

        if ($this->import && (bool) config('role-matrix.filament.enable_import', true)) {
            $pages[] = FilamentVersionAdapter::importPage();
        }

        if ($this->debugger && (bool) config('role-matrix.filament.enable_debugger', true)) {
            $pages[] = FilamentVersionAdapter::debuggerPage();
        }

        $panel->pages($pages)->resources([
            $this->roleResource ?? FilamentVersionAdapter::roleResource(),
        ])->widgets([
            Widgets\AccessOverviewWidget::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        if (class_exists(FilamentAsset::class)) {
            FilamentAsset::register([
                Css::make('role-matrix', __DIR__.'/../../resources/css/role-matrix.css'),
                Js::make('role-matrix', __DIR__.'/../../resources/js/role-matrix.js'),
            ], 'imrandevbd/laravel-role-matrix');
        }
    }
}
