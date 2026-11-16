<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use ImranDevBD\RoleMatrix\RoleMatrixServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [RoleMatrixServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'RoleMatrix' => \ImranDevBD\RoleMatrix\Facades\RoleMatrix::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('role-matrix.cache.store', 'array');
        $app['config']->set('role-matrix.modules.auto_sync_on_boot', false);
        $app['config']->set('role-matrix.modules.paths', []);
        $app['config']->set('role-matrix.models.user', Fixtures\User::class);
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);
    }

    protected function afterRefreshingDatabase(): void
    {
        $schema = $this->app['db']->getSchemaBuilder();

        if ($schema->hasTable('users')) {
            return;
        }

        $schema->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('guard_name')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamps();
        });
    }
}
