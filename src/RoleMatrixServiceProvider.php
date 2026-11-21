<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use ImranDevBD\RoleMatrix\Conditions\ConditionEngine;
use ImranDevBD\RoleMatrix\Conditions\ConditionRegistry;
use ImranDevBD\RoleMatrix\Contracts\AuditServiceContract;
use ImranDevBD\RoleMatrix\Contracts\CacheServiceContract;
use ImranDevBD\RoleMatrix\Contracts\CloneServiceContract;
use ImranDevBD\RoleMatrix\Contracts\ImportExportServiceContract;
use ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract;
use ImranDevBD\RoleMatrix\Contracts\RoleServiceContract;
use ImranDevBD\RoleMatrix\Contracts\SyncServiceContract;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Observers\RoleObserver;
use ImranDevBD\RoleMatrix\Registry\ModuleRegistry;
use ImranDevBD\RoleMatrix\Services\AssignmentService;
use ImranDevBD\RoleMatrix\Services\AuditService;
use ImranDevBD\RoleMatrix\Services\CacheService;
use ImranDevBD\RoleMatrix\Services\SuperAdminGuard;
use ImranDevBD\RoleMatrix\Support\AuditContext;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\SubjectResolver;
use ImranDevBD\RoleMatrix\Support\TeamContext;

final class RoleMatrixServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/role-matrix.php', 'role-matrix');

        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(TeamContext::class);
        $this->app->singleton(AuditContext::class);
        $this->app->singleton(ConditionRegistry::class);
        $this->app->singleton(ConditionEngine::class);
        $this->app->singleton(SubjectResolver::class);
        $this->app->singleton(CacheService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(SuperAdminGuard::class);
        $this->app->singleton(AssignmentService::class);

        $this->bindConfigured(CacheServiceContract::class, 'cache');
        $this->bindConfigured(PermissionResolverContract::class, 'resolver');
        $this->bindConfigured(RoleServiceContract::class, 'roles');
        $this->bindConfigured(SyncServiceContract::class, 'sync');
        $this->bindConfigured(CloneServiceContract::class, 'clone');
        $this->bindConfigured(ImportExportServiceContract::class, 'import_export');
        $this->bindConfigured(AuditServiceContract::class, 'audit');

        $this->app->singleton(RoleMatrix::class, function ($app): RoleMatrix {
            return new RoleMatrix(
                $app->make(ModuleRegistry::class),
                $app->make(PermissionResolverContract::class),
                $app->make(TeamContext::class),
            );
        });
        $this->app->alias(RoleMatrix::class, 'role-matrix');

        if (RoleMatrixConfig::engine() === 'spatie') {
            $this->app->register(Spatie\SpatieCompatibilityServiceProvider::class);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/role-matrix.php' => config_path('role-matrix.php'),
        ], 'role-matrix-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'role-matrix-migrations');

        if (is_dir(__DIR__.'/../lang')) {
            $this->loadTranslationsFrom(__DIR__.'/../lang', 'role-matrix');
            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/role-matrix'),
            ], 'role-matrix-lang');
        }

        if (is_dir(__DIR__.'/../resources/views')) {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'role-matrix');
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/role-matrix'),
            ], 'role-matrix-views');
        }

        if (config('role-matrix.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->commands($this->commandClasses());
        }

        $this->loadRoutes();
        $this->registerGate();
        $this->registerBlade();
        $this->registerMiddleware();
        $this->registerObservers();
        $this->registerReset();
        $this->registerSchedule();
        $this->registerAbout();
        $this->registerNotifications();

        $this->app->booted(function (): void {
            $this->registerSystemModule();
            $this->app->make(ModuleRegistry::class)->discover();
        });
    }

    private function bindConfigured(string $contract, string $key): void
    {
        $this->app->singleton($contract, function ($app) use ($key) {
            $class = RoleMatrixConfig::service($key);

            return $app->make($class);
        });
    }

    private function loadRoutes(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')->group(function (): void {
            \Illuminate\Support\Facades\Route::get('/role-matrix-assets/role-matrix.css', function () {
                return response()->file(__DIR__.'/../resources/css/role-matrix.css', ['Content-Type' => 'text/css']);
            })->name('role-matrix.assets.css');

            \Illuminate\Support\Facades\Route::get('/role-matrix-assets/role-matrix.js', function () {
                return response()->file(__DIR__.'/../resources/js/role-matrix.js', ['Content-Type' => 'application/javascript']);
            })->name('role-matrix.assets.js');

            \Illuminate\Support\Facades\Route::post('/role-matrix-assets/import', [Http\Controllers\ImportController::class, 'store'])
                ->middleware('auth')
                ->name('role-matrix.import');
        });

        if (config('role-matrix.standalone_ui.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/role-matrix.php');
        }

        if (config('role-matrix.api.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
    }

    private function registerGate(): void
    {
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if (! is_object($user) || ! method_exists($user, 'hasPermissionTo')) {
                return null;
            }

            $record = $arguments[0] ?? null;

            return $this->app->make(PermissionResolverContract::class)->gate($user, $ability, $record);
        });
    }

    private function registerBlade(): void
    {
        if (! class_exists(Blade::class)) {
            return;
        }

        $userCan = static function (string $method, array $arguments): bool {
            $user = auth()->user();

            return $user !== null && method_exists($user, $method) && (bool) $user->{$method}(...$arguments);
        };

        Blade::if('role', fn (mixed ...$roles): bool => $userCan('hasAnyRole', $roles));
        Blade::if('hasanyrole', fn (mixed ...$roles): bool => $userCan('hasAnyRole', $roles));
        Blade::if('hasallroles', fn (mixed ...$roles): bool => $userCan('hasAllRoles', $roles));
        Blade::if('permission', fn (mixed $permission, mixed $record = null): bool => $userCan('hasPermissionTo', [$permission, $record]));
        Blade::if('anypermission', fn (mixed ...$permissions): bool => $userCan('hasAnyPermission', $permissions));
        Blade::if('canmatrix', fn (mixed $permission, mixed $record = null): bool => $userCan('hasPermissionTo', [$permission, $record]));
        Blade::if('superadmin', fn (): bool => $userCan('isSuperAdmin', []));
    }

    private function registerMiddleware(): void
    {
        if (! (bool) config('role-matrix.middleware.register_aliases', true)) {
            return;
        }

        $router = $this->app->make('router');
        $router->aliasMiddleware('role', Middleware\RoleMiddleware::class);
        $router->aliasMiddleware('permission', Middleware\PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', Middleware\RoleOrPermissionMiddleware::class);
        $router->aliasMiddleware('matrix', Middleware\MatrixMiddleware::class);
        $router->aliasMiddleware('role-matrix.team', Middleware\SetTeamMiddleware::class);
    }

    private function registerObservers(): void
    {
        Role::observe(RoleObserver::class);
    }

    private function registerReset(): void
    {
        Event::listen(RequestHandled::class, function (): void {
            $this->app->make(CacheService::class)->flushMemo();
            $this->app->make(TeamContext::class)->reset();
            $this->app->make(AuditContext::class)->reset();
        });

        if (class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            Event::listen(\Laravel\Octane\Events\RequestReceived::class, function (): void {
                $this->app->make(CacheService::class)->flushMemo();
                $this->app->make(TeamContext::class)->reset();
                $this->app->make(AuditContext::class)->reset();
            });
        }
    }

    private function registerSchedule(): void
    {
        if (! (bool) config('role-matrix.audit.prune_daily', false)) {
            return;
        }

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('role-matrix:prune-audit')->daily();
        });
    }

    private function registerNotifications(): void
    {
        if (! config('role-matrix.events.enabled', true)) {
            return;
        }

        if (! config('role-matrix.events.mail_to') && ! config('role-matrix.events.slack_webhook')) {
            return;
        }

        foreach ([
            Events\RoleCreated::class,
            Events\RoleDeleted::class,
            Events\PermissionsSynced::class,
            Events\RoleAssignedToUser::class,
            Events\SuperAdminProtected::class,
        ] as $event) {
            Event::listen($event, Listeners\NotifyAccessChange::class);
        }
    }

    private function registerAbout(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Role Matrix', fn (): array => [
            'Version' => RoleMatrix::VERSION,
            'Engine' => RoleMatrixConfig::engine(),
            'Guard' => RoleMatrixConfig::defaultGuard(),
        ]);
    }

    private function registerSystemModule(): void
    {
        $this->app->make(RoleMatrix::class)
            ->module('role-matrix', true)
            ->label('role-matrix::modules.role_matrix.label')
            ->description('role-matrix::modules.role_matrix.description')
            ->group('role-matrix::groups.access')
            ->icon('heroicon-o-shield-check')
            ->actions(['view', 'manage', 'audit', 'export', 'import'])
            ->sort(0);
    }

    /**
     * @return list<class-string>
     */
    private function commandClasses(): array
    {
        $commands = [
            Console\Commands\InstallCommand::class,
            Console\Commands\SyncPermissionsCommand::class,
            Console\Commands\CreateSuperAdminCommand::class,
            Console\Commands\MakeModuleCommand::class,
            Console\Commands\MakePermissionEnumCommand::class,
            Console\Commands\MakePolicyCommand::class,
            Console\Commands\CacheCommand::class,
            Console\Commands\CacheClearCommand::class,
            Console\Commands\ExportCommand::class,
            Console\Commands\ImportCommand::class,
            Console\Commands\ListRolesCommand::class,
            Console\Commands\ListPermissionsCommand::class,
            Console\Commands\DoctorCommand::class,
            Console\Commands\PruneAuditCommand::class,
        ];

        return array_values(array_filter($commands, 'class_exists'));
    }
}
