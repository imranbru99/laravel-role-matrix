<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\Concerns;

use Filament\Notifications\Notification;
use ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract;
use ImranDevBD\RoleMatrix\Contracts\SyncServiceContract;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Services\AssignmentService;
use ImranDevBD\RoleMatrix\Services\MatrixBuilder;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

trait InteractsWithMatrixPage
{
    public string $guard = '';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $requested = (string) request()->query('guard', '');
        $pluginGuard = self::pluginGuard();
        $this->guard = in_array($requested, RoleMatrixConfig::guards(), true)
            ? $requested
            : ($pluginGuard !== '' ? $pluginGuard : RoleMatrixConfig::defaultGuard());
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $plugin = self::plugin();

        if ($plugin instanceof \ImranDevBD\RoleMatrix\Filament\RoleMatrixPlugin && ! $plugin->allows($user)) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('role-matrix.view');
    }

    private static function plugin(): ?\ImranDevBD\RoleMatrix\Filament\RoleMatrixPlugin
    {
        if (! class_exists(\Filament\Facades\Filament::class)) {
            return null;
        }

        try {
            $plugin = \Filament\Facades\Filament::getCurrentPanel()?->getPlugin('role-matrix');
        } catch (\Throwable) {
            return null;
        }

        return $plugin instanceof \ImranDevBD\RoleMatrix\Filament\RoleMatrixPlugin ? $plugin : null;
    }

    private static function pluginGuard(): string
    {
        return self::plugin()?->configuredGuard() ?? '';
    }

    public static function getNavigationLabel(): string
    {
        return __('role-matrix::ui.matrix');
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) config('role-matrix.filament.navigation_group', 'Access Control');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('role-matrix.filament.navigation_sort', 20);
    }

    /**
     * @return array<string, mixed>
     */
    protected function matrixPayload(): array
    {
        return app(MatrixBuilder::class)->payload($this->guard);
    }

    /**
     * @param  list<array{role_id: string, grant?: list<string>, revoke?: list<string>, conditions?: array<string, mixed>}>  $changes
     */
    public function save(array $changes): void
    {
        abort_unless(static::canAccess(), 403);
        $assignments = app(AssignmentService::class);

        foreach ($changes as $change) {
            $role = Role::query()->find($change['role_id'] ?? null);

            if (! $role instanceof Role) {
                continue;
            }

            $assignments->applyRoleDiff(
                $role,
                array_values($change['grant'] ?? []),
                array_values($change['revoke'] ?? []),
                is_array($change['conditions'] ?? null) ? $change['conditions'] : [],
            );
        }

        if (class_exists(Notification::class)) {
            Notification::make()->title(__('role-matrix::ui.saved'))->success()->send();
        }
    }

    public function syncPermissions(): void
    {
        abort_unless(static::canAccess(), 403);
        $report = app(SyncServiceContract::class)->sync($this->guard, false, false);

        if (class_exists(Notification::class)) {
            Notification::make()
                ->title(__('role-matrix::ui.synced'))
                ->body('+'.count($report->added).' / -'.count($report->removed))
                ->success()
                ->send();
        }
    }

    public function explain(string $userId, string $permission, ?string $record = null): void
    {
        abort_unless(static::canAccess(), 403);
        $model = RoleMatrixConfig::model('user');
        $user = $model::query()->findOrFail($userId);
        $explanation = app(PermissionResolverContract::class)->explain($user, $permission, $record);
        $this->dispatch('role-matrix-explained', explanation: $explanation->toArray());
    }
}
