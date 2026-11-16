<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ImranDevBD\RoleMatrix\Contracts\CloneServiceContract;
use ImranDevBD\RoleMatrix\Contracts\ImportExportServiceContract;
use ImranDevBD\RoleMatrix\Contracts\RoleServiceContract;
use ImranDevBD\RoleMatrix\Exceptions\RoleInUseException;
use ImranDevBD\RoleMatrix\Facades\RoleMatrix;
use ImranDevBD\RoleMatrix\Middleware\PermissionMiddleware;
use ImranDevBD\RoleMatrix\Models\AuditLog;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Services\AssignmentService;
use ImranDevBD\RoleMatrix\Services\MatrixBuilder;
use ImranDevBD\RoleMatrix\Support\Checksum;
use ImranDevBD\RoleMatrix\Tests\Fixtures\Invoice;
use ImranDevBD\RoleMatrix\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => Hash::make('secret'),
        'team_id' => 7,
    ]);
    $this->user->team_id = 7;
    $this->user->department_id = 3;
});

it('evaluates every built-in condition and all/any nesting', function () {
    $assignments = app(AssignmentService::class);
    $own = new Invoice(['user_id' => $this->user->id, 'team_id' => 7, 'department_id' => 3, 'status' => 'pending', 'total' => 100, 'currency' => 'BDT']);
    $foreign = new Invoice(['user_id' => 99, 'team_id' => 1, 'department_id' => 9, 'status' => 'posted', 'total' => 90000, 'currency' => 'BDT']);

    $assignments->give($this->user, ['invoices.view'], ['all' => [['rule' => 'ownership', 'owner_key' => 'user_id']]]);
    $assignments->give($this->user, ['invoices.update'], ['all' => [['rule' => 'team', 'team_key' => 'team_id']]]);
    $assignments->give($this->user, ['invoices.create'], ['all' => [['rule' => 'department', 'department_key' => 'department_id']]]);
    $assignments->give($this->user, ['invoices.approve'], ['all' => [['rule' => 'status', 'field' => 'status', 'operator' => 'in', 'values' => 'pending']]]);
    $assignments->give($this->user, ['invoices.export'], ['all' => [['rule' => 'amount', 'field' => 'total', 'operator' => 'lte', 'value' => 50000]]]);
    $assignments->give($this->user, ['invoices.print'], ['all' => [['rule' => 'time_window', 'days' => '0,1,2,3,4,5,6', 'start' => '00:00', 'end' => '23:59']]]);
    $assignments->give($this->user, ['invoices.delete'], ['all' => [['rule' => 'ip_range', 'cidrs' => '127.0.0.1/32']]]);
    $assignments->give($this->user, ['invoices.submit'], ['all' => [['rule' => 'attribute', 'field' => 'status', 'operator' => 'eq', 'value_source' => 'literal', 'value' => 'pending']]]);
    RoleMatrix::condition('flagged', fn (): bool => true, 'Flagged');
    $assignments->give($this->user, ['invoices.restore'], ['all' => [['rule' => 'flagged']]]);
    $assignments->give($this->user, ['invoices.force'], [
        'all' => [['rule' => 'ownership', 'owner_key' => 'user_id']],
        'any' => [
            ['rule' => 'status', 'field' => 'status', 'values' => 'posted'],
            ['rule' => 'status', 'field' => 'status', 'values' => 'pending'],
        ],
    ]);

    expect($this->user->hasPermissionTo('invoices.view', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.view', $foreign))->toBeFalse()
        ->and($this->user->hasPermissionTo('invoices.view'))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.update', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.update', $foreign))->toBeFalse()
        ->and($this->user->hasPermissionTo('invoices.create', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.create', $foreign))->toBeFalse()
        ->and($this->user->hasPermissionTo('invoices.approve', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.approve', $foreign))->toBeFalse()
        ->and($this->user->hasPermissionTo('invoices.export', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.export', $foreign))->toBeFalse()
        ->and($this->user->hasPermissionTo('invoices.print', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.delete', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.submit', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.submit', $foreign))->toBeFalse()
        ->and($this->user->hasPermissionTo('invoices.restore', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.force', $own))->toBeTrue()
        ->and($this->user->hasPermissionTo('invoices.force', $foreign))->toBeFalse();

    $closed = ['all' => [['rule' => 'time_window', 'start_date' => '1990-01-01', 'end_date' => '1990-01-02']]];
    $assignments->give($this->user, ['invoices.archive'], $closed);
    expect($this->user->hasPermissionTo('invoices.archive', $own))->toBeFalse();
});

it('turns ownership and status rules into SQL', function () {
    Schema::create('invoices', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('status');
        $table->integer('total')->default(0);
    });

    $mine = Invoice::query()->create(['user_id' => $this->user->id, 'status' => 'pending', 'total' => 10]);
    Invoice::query()->create(['user_id' => 99, 'status' => 'pending', 'total' => 10]);
    Invoice::query()->create(['user_id' => $this->user->id, 'status' => 'posted', 'total' => 10]);

    app(AssignmentService::class)->give($this->user, ['invoices.view'], [
        'all' => [
            ['rule' => 'ownership', 'owner_key' => 'user_id'],
            ['rule' => 'status', 'field' => 'status', 'values' => 'pending'],
        ],
    ]);

    $ids = Invoice::query()->accessibleBy($this->user, 'invoices.view')->pluck('id')->all();

    expect($ids)->toBe([$mine->id]);
});

it('blocks and allows the permission middleware and blade directives', function () {
    $this->user->givePermissionTo('reports.view');
    $this->user->assignRole(Role::factory()->create(['name' => 'Analyst', 'slug' => 'analyst']));
    $this->actingAs($this->user);

    $middleware = app(PermissionMiddleware::class);
    $allowed = Request::create('/reports', 'GET');
    $allowed->setUserResolver(fn () => $this->user);
    $response = $middleware->handle($allowed, fn () => response('ok'), 'reports.view');
    expect($response->getContent())->toBe('ok');

    $denied = Request::create('/secret', 'GET');
    $denied->setUserResolver(fn () => $this->user);
    expect(fn () => $middleware->handle($denied, fn () => response('ok'), 'reports.export'))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect(Blade::render("@permission('reports.view') yes @else no @endpermission"))->toContain('yes')
        ->and(Blade::render("@role('analyst') yes @else no @endrole"))->toContain('yes')
        ->and(Blade::render('@superadmin yes @else no @endsuperadmin'))->toContain('no');
});

it('clones, soft-deletes with reassignment, and writes an audit row', function () {
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::factory()->create(['name' => 'posts.update', 'module_slug' => 'posts', 'action' => 'update']);
    $role->permissions()->attach($permission->id, ['granted_at' => now(), 'conditions' => ['all' => [['rule' => 'ownership']]]]);
    $this->user->assignRole($role);
    expect(User::query()->role('editor')->pluck('id')->all())->toBe([$this->user->id]);

    $copy = app(CloneServiceContract::class)->clone($role, ['name' => 'Editor copy', 'copy_conditions' => true]);
    expect($copy->slug)->not->toBe('editor')
        ->and($copy->permissions()->count())->toBe(1)
        ->and($copy->is_super_admin)->toBeFalse();

    $fallback = Role::factory()->create(['name' => 'Fallback', 'slug' => 'fallback']);
    expect(fn () => app(RoleServiceContract::class)->delete($role))->toThrow(RoleInUseException::class);

    app(RoleServiceContract::class)->delete($role, $fallback);
    expect(Role::query()->find($role->id))->toBeNull()
        ->and(Role::withTrashed()->find($role->id))->not->toBeNull()
        ->and($this->user->fresh()->hasRole('fallback'))->toBeTrue();

    app(RoleServiceContract::class)->restore($role);
    expect(Role::query()->find($role->id))->not->toBeNull()
        ->and(AuditLog::query()->count())->toBeGreaterThan(0);
});

it('round-trips an export and keeps the checksum', function () {
    $role = Role::factory()->create(['name' => 'Auditor', 'slug' => 'auditor']);
    $permission = Permission::factory()->create(['name' => 'reports.view', 'module_slug' => 'reports', 'action' => 'view']);
    $role->permissions()->attach($permission->id, ['granted_at' => now()]);

    $payload = app(ImportExportServiceContract::class)->export('web', ['auditor']);
    expect(Checksum::valid($payload))->toBeTrue()
        ->and($payload['schema'])->toBe('role-matrix/1');

    $role->permissions()->detach();
    app(ImportExportServiceContract::class)->import($payload, 'merge');

    expect($role->fresh()->permissions()->pluck('name')->all())->toBe(['reports.view']);
});

it('syncs from the registry idempotently and the doctor reports a super admin', function () {
    RoleMatrix::module('warehouse')->label('Warehouse')->group('Operations')->crud();

    $this->artisan('role-matrix:sync-permissions', ['--dry-run' => true, '--guard' => 'web', '--json' => true])
        ->assertSuccessful();
    expect(Permission::query()->where('module_slug', 'warehouse')->count())->toBe(0);

    $this->artisan('role-matrix:sync-permissions', ['--guard' => 'web'])->assertSuccessful();
    $count = Permission::query()->where('module_slug', 'warehouse')->count();
    expect($count)->toBe(4);

    $this->artisan('role-matrix:sync-permissions', ['--guard' => 'web'])->assertSuccessful();
    expect(Permission::query()->where('module_slug', 'warehouse')->count())->toBe($count);

    app(RoleServiceContract::class)->seedDefaults('web');
    $this->artisan('role-matrix:doctor', ['--json' => true])->assertSuccessful();
    $this->artisan('role-matrix:list-roles', ['--json' => true])->assertSuccessful();
    $this->artisan('role-matrix:cache:clear')->assertSuccessful();
});

it('renders the matrix from one payload', function () {
    Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);
    RoleMatrix::module('inventory')->label('Inventory')->crud();

    $html = view('role-matrix::matrix', [
        'payload' => app(MatrixBuilder::class)->payload('web'),
        'saveUrl' => '/role-matrix',
    ])->render();

    expect($html)->toContain('data-rm')
        ->and($html)->toContain('Inventory')
        ->and($html)->toContain('role-matrix-assets/role-matrix.css');
});
