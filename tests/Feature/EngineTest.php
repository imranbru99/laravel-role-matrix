<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use ImranDevBD\RoleMatrix\Exceptions\CircularDependencyException;
use ImranDevBD\RoleMatrix\Exceptions\InvalidModuleException;
use ImranDevBD\RoleMatrix\Facades\RoleMatrix;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => Hash::make('secret'),
    ]);
});

it('registers modules, presets, and rejects duplicates and cycles', function () {
    RoleMatrix::module('inventory')->label('Inventory')->group('Operations')->crud()->sort(20);
    RoleMatrix::module('products')->readOnly();
    RoleMatrix::module('inventory', true)->dependsOn('products');

    expect(RoleMatrix::registry()->find('inventory')->actionsMap())->toHaveKey('delete')
        ->and(RoleMatrix::registry()->requirements()['inventory.delete'])->toContain('inventory.view', 'products.view');

    RoleMatrix::module('products', true)->dependsOn('inventory');
})->throws(CircularDependencyException::class);

it('rejects a second registration of the same slug', function () {
    RoleMatrix::module('sales')->crud();
    RoleMatrix::module('sales')->crud();
})->throws(InvalidModuleException::class);

it('unions role grants, inherits parents, and lets a direct deny win', function () {
    $view = Permission::factory()->create([
        'name' => 'inventory.view',
        'module_slug' => 'inventory',
        'action' => 'view',
        'guard_name' => 'web',
    ]);
    $update = Permission::factory()->create([
        'name' => 'inventory.update',
        'module_slug' => 'inventory',
        'action' => 'update',
        'guard_name' => 'web',
    ]);

    $viewer = Role::factory()->create(['name' => 'Viewer', 'slug' => 'viewer', 'guard_name' => 'web', 'level' => 10]);
    $staff = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff', 'guard_name' => 'web', 'parent_id' => $viewer->id, 'level' => 20]);
    $viewer->permissions()->attach($view->id, ['granted_at' => now()]);
    $staff->permissions()->attach($update->id, ['granted_at' => now()]);

    $this->user->assignRole($staff);

    expect($this->user->hasPermissionTo('inventory.view'))->toBeTrue()
        ->and($this->user->hasPermissionTo('inventory.update'))->toBeTrue()
        ->and($this->user->can('inventory.update'))->toBeTrue()
        ->and($this->user->can('update'))->toBeFalse();

    $this->user->denyPermissionTo('inventory.update');

    expect($this->user->hasPermissionTo('inventory.update'))->toBeFalse()
        ->and($this->user->can('inventory.update'))->toBeFalse()
        ->and($this->user->hasPermissionTo('inventory.view'))->toBeTrue();
});

it('matches wildcard grants and isolates guards', function () {
    $this->user->givePermissionTo('inventory.*');

    expect($this->user->hasPermissionTo('inventory.delete'))->toBeTrue()
        ->and($this->user->hasPermissionTo('sales.view'))->toBeFalse();

    $api = Permission::factory()->create([
        'name' => 'reports.view',
        'module_slug' => 'reports',
        'action' => 'view',
        'guard_name' => 'api',
    ]);
    $role = Role::factory()->create(['name' => 'Api', 'slug' => 'api-user', 'guard_name' => 'api']);
    $role->permissions()->attach($api->id, ['granted_at' => now()]);

    expect($this->user->hasPermissionTo('reports.view'))->toBeFalse();
});

it('expires temporary roles and protects the super admin', function () {
    $role = Role::factory()->create(['name' => 'Temp', 'slug' => 'temp', 'guard_name' => 'web']);
    Permission::factory()->create(['name' => 'notes.view', 'module_slug' => 'notes', 'action' => 'view']);
    $role->permissions()->attach(
        Permission::query()->where('name', 'notes.view')->value('id'),
        ['granted_at' => now()],
    );

    $this->user->assignTemporaryRole($role, now()->subMinute());

    expect($this->user->hasRole('temp'))->toBeFalse()
        ->and($this->user->hasPermissionTo('notes.view'))->toBeFalse();

    $super = Role::factory()->superAdmin()->create(['guard_name' => 'web']);
    $this->user->assignRole($super);

    expect($this->user->isSuperAdmin())->toBeTrue()
        ->and($this->user->can('settings.destroy'))->toBeTrue();

    expect(fn () => app(ImranDevBD\RoleMatrix\Contracts\RoleServiceContract::class)->delete($super, null, true))
        ->toThrow(ImranDevBD\RoleMatrix\Exceptions\SuperAdminProtectedException::class);

    expect(fn () => $this->user->removeRole($super))
        ->toThrow(ImranDevBD\RoleMatrix\Exceptions\SuperAdminProtectedException::class);
});

it('resolves a wide matrix in a handful of queries', function () {
    $modules = [];

    for ($i = 1; $i <= 100; $i++) {
        $modules[] = RoleMatrix::module('mod'.$i)->crud();
    }

    app(ImranDevBD\RoleMatrix\Contracts\SyncServiceContract::class)->sync('web');

    $permissions = Permission::query()->where('guard_name', 'web')->pluck('id');
    $holder = Role::factory()->create(['name' => 'Holder', 'slug' => 'holder', 'guard_name' => 'web']);

    foreach ($permissions->chunk(200) as $chunk) {
        $holder->permissions()->attach($chunk->all(), ['granted_at' => now()]);
    }

    for ($i = 1; $i <= 19; $i++) {
        Role::factory()->create(['name' => 'Role '.$i, 'slug' => 'role-'.$i, 'guard_name' => 'web']);
    }

    $this->user->assignRole($holder);
    app(ImranDevBD\RoleMatrix\Services\CacheService::class)->forgetAll();

    DB::flushQueryLog();
    DB::enableQueryLog();
    expect($this->user->hasPermissionTo('mod1.view'))->toBeTrue();
    $cold = count(DB::getQueryLog());

    DB::flushQueryLog();
    expect($this->user->hasPermissionTo('mod100.delete'))->toBeTrue();
    $warm = count(DB::getQueryLog());

    expect($cold)->toBeLessThanOrEqual(5)
        ->and($warm)->toBeLessThanOrEqual(1);
});
