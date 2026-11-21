# Role Matrix

Role and permission management for Laravel. The part you actually use is a Role × Module × Action matrix: register a module in a few lines, sync, and let an admin grant access visually.

Native tables are the source of truth. Hierarchy, direct allow/deny, temporary roles, teams, and per-grant conditions live there. An optional Spatie bridge can mirror flat names into `spatie/laravel-permission` for apps that already call that API. Filament is optional and is not a Composer requirement of the core.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- MySQL 8, MariaDB 10.6+, PostgreSQL, or SQLite
- Filament 3.2 or 4, only if you want the admin UI

## Install

```bash
composer require imrandevbd/laravel-role-matrix
php artisan role-matrix:install
```

Add the trait to your user model:

```php
use ImranDevBD\RoleMatrix\Traits\HasRoleMatrix;

class User extends Authenticatable
{
    use HasRoleMatrix;
}
```

Register modules, sync, and open the matrix:

```php
use ImranDevBD\RoleMatrix\Facades\RoleMatrix;

RoleMatrix::module('inventory')
    ->label('Inventory')
    ->icon('heroicon-o-cube')
    ->group('Operations')
    ->description('Stock, warehouses and transfers')
    ->crud()
    ->action('approve', label: 'Approve', danger: true)
    ->conditionable(['ownership', 'status', 'amount'])
    ->dependsOn('products')
    ->sort(20);

php artisan role-matrix:sync-permissions
```

With Filament:

```php
->plugin(RoleMatrixPlugin::make()->navigationGroup('Access Control')->guard('web'))
```

The matrix is then at `/admin/role-matrix`. Without Filament, set `ROLE_MATRIX_STANDALONE=true` and open `/role-matrix`.

## What you get

- A module registry with `crud`, `readOnly`, `workflow`, and `reportable` presets, dependencies, and auto-discovery under `app/RoleMatrix/Modules`.
- Effective permissions: direct deny beats direct allow, then role grants, then inherited parent grants. Super Admin short-circuits.
- `Gate::before` for dotted abilities such as `inventory.update`, so `@can` and policies keep working.
- Conditions on a grant: ownership, team, department, status, amount, time window, IP range, attribute, or a custom class.
- `scopeAccessibleBy($user, 'invoices.view')` compiles ownership, team, status, and literal attribute rules to SQL.
- Soft-deleted roles, clone, JSON import/export (`schema: role-matrix/1` plus a checksum), and an audit log.
- English and Bangla translations.

## Permissions and wildcards

Keys are `module.action`. Grants may be `inventory.*`, `*.view`, `*.*`, or `*`. Wildcards are compiled once. A direct deny still wins over a wildcard allow.

```php
$user->givePermissionTo('inventory.*');
$user->denyPermissionTo('inventory.delete');
$user->hasPermissionTo('inventory.update'); // true
$user->hasPermissionTo('inventory.delete'); // false
```

## Hierarchy

`parent_id` means "also grant that role's permissions". The seeded chain is Viewer (default for new users) ← Staff ← Manager ← Admin. Super Admin has no parent and cannot be placed under another role. A child cannot become its own ancestor.

## Conditions

```php
$role->permissions()->syncWithoutDetaching([
    $permission->id => ['conditions' => [
        'all' => [
            ['rule' => 'ownership', 'owner_key' => 'user_id'],
            ['rule' => 'status', 'field' => 'status', 'values' => 'pending'],
            ['rule' => 'amount', 'field' => 'total', 'operator' => 'lte', 'value' => 50000],
        ],
    ]],
]);
```

`$user->can('invoices.approve', $invoice)` evaluates the rules. With no record, a conditional grant stays allowed so menus can render, unless `role-matrix.abac.strict` is true.

`RoleMatrix::explain($user, 'invoices.approve', $invoice)` returns the matching grant and the rule trace. The Access Debugger page shows the same payload.

Own a rule:

```php
RoleMatrix::condition('weekday', fn ($context): bool => now()->isWeekday(), 'Weekday');
```

## Gate, policies, middleware, Blade

```php
$user->can('inventory.update', $product);

class InvoicePolicy extends \ImranDevBD\RoleMatrix\Policies\BasePolicy {}

Route::post('/invoices/{invoice}/approve', ApproveInvoice::class)
    ->middleware('matrix:invoices.approve,invoice');

@permission('inventory.view')
    <a href="/inventory">Inventory</a>
@endpermission
```

Aliases: `role`, `permission`, `role_or_permission`, `matrix`, `role-matrix.team`.

```blade
@role('manager') ... @endrole
@hasanyrole('manager', 'staff') ... @endhasanyrole
@hasallroles('manager', 'staff') ... @endhasallroles
@permission('inventory.view', $record) ... @endpermission
@anypermission('inventory.view', 'inventory.export') ... @endanypermission
@canmatrix('invoices.approve', $invoice) ... @endcanmatrix
@superadmin ... @endsuperadmin
```

Generate a backed enum after syncing modules:

```bash
php artisan role-matrix:make-permission-enum
```

```php
use App\RoleMatrix\Permission;

$user->hasPermissionTo(Permission::InventoryView);
```

The optional JSON API is off until `ROLE_MATRIX_API=true`. It serves `GET /api/role-matrix/roles`, `GET /api/role-matrix/permissions`, and `POST /api/role-matrix/explain`. The caller must be a super admin or hold `role-matrix.view`.

Keys default to integers. Set `ROLE_MATRIX_KEY_TYPE` to `ulid` or `uuid` before migrating. Set `ROLE_MATRIX_TABLE_PREFIX` when the table names would collide with another package.

## Filament

```php
RoleMatrixPlugin::make()
    ->navigationGroup('Access Control')
    ->guard('web')
    ->enableAuditLog()
    ->enableImportExport()
    ->canAccess(fn ($user) => $user->isSuperAdmin())
    ->roleResource(CustomRoleResource::class);
```

Version differences sit behind `FilamentVersionAdapter`. The matrix holds edits in the browser and saves them in one request. Super Admin columns are locked in the UI and in the service layer. Focus mode edits one role as cards. The condition drawer builds ownership, team, status, amount, time, IP, and attribute rules field by field.

Drop these into a Filament user table:

```php
use ImranDevBD\RoleMatrix\Filament\Actions\AssignRolesAction;
use ImranDevBD\RoleMatrix\Filament\Tables\RolesColumn;
use ImranDevBD\RoleMatrix\Filament\Tables\RolesFilter;

RolesColumn::make();
RolesFilter::make();
AssignRolesAction::make();
```

The same plugin also registers the audit log, import/export page, and access debugger. `role-matrix:doctor` reports a missing super admin, unsynced modules, orphan permissions, hierarchy cycles, and guard mismatches.

## Guards, teams, cache

Every role and permission belongs to a guard. `RoleMatrix::forGuard('api')` scopes the manager. Temporary assignments honor `expires_at`. Teams use `scope_key` (empty string when global, because SQL `NULL` is not unique).

The cache is versioned. Stores that support tags are flushed as well. The version key itself is not stored inside the tag. Memoized permissions reset at the end of the request and on Octane `RequestReceived`. A cache outage does not make `can()` fail.

## Events

`RoleCreated`, `RoleUpdated`, `RoleDeleted`, `RoleRestored`, `RoleCloned`, `PermissionsSynced`, `PermissionsGrantedToRole`, `PermissionsRevokedFromRole`, `RoleAssignedToUser`, `RoleRemovedFromUser`, `ModulesSynced`, `RolesImported`, `SuperAdminProtected`.

Set `ROLE_MATRIX_NOTIFY_MAIL` or `ROLE_MATRIX_SLACK_WEBHOOK` to send the bundled listener. Leave both empty and nothing is queued.

## Commands

| Command | Purpose |
| --- | --- |
| `role-matrix:install` | Publish config, migrate, seed default roles, offer a super admin |
| `role-matrix:sync-permissions` | Create permissions from the registry (`--dry-run`, `--prune`, `--guard`) |
| `role-matrix:create-super-admin` | Create a user and attach the protected role |
| `role-matrix:make-module` | Scaffold a module class |
| `role-matrix:make-permission-enum` | Write `App\RoleMatrix\Permission` from the registry |
| `role-matrix:make-policy` | Scaffold a policy that extends `BasePolicy` |
| `role-matrix:cache` / `role-matrix:cache:clear` | Warm or drop the registry and permission cache |
| `role-matrix:export` / `role-matrix:import` | JSON with checksum. Modes: `merge`, `replace`, `dry-run` |
| `role-matrix:list-roles` / `role-matrix:list-permissions` | Inspection, `--json` for CI |
| `role-matrix:doctor` | Super admin, unsynced modules, orphans, cycles, guards, cache tags |
| `role-matrix:prune-audit` | Delete audit rows older than the retention window |

## Import schema

```json
{
  "schema": "role-matrix/1",
  "exported_at": "2026-11-16T00:00:00+00:00",
  "roles": [],
  "checksum": "sha256"
}
```

Unknown permissions are reported. They are created only with `--create-missing`. Replace mode refuses to remove the Super Admin role.

## Spatie

Keep `ROLE_MATRIX_ENGINE=native` unless you already have Spatie. The bridge mirrors names after sync and assignment. It does not mirror conditions or hierarchy. If both packages would use a table named `roles`, set `ROLE_MATRIX_TABLE_PREFIX=rm_` first. Do not put `HasRoleMatrix` and Spatie's `HasRoles` on the same model.

## Security

Super Admin cannot be renamed, deactivated, deleted, or stripped, and the last super-admin user cannot be detached. The same checks run in the service, the model observer, and the UI. A bypass exists only when `role-matrix.super_admin.bypass_ability` is set and the current user is allowed that ability. The matrix and the import endpoint require an authenticated user who is a super admin or holds `role-matrix.view` / `role-matrix.import`.

## Demo

```bash
composer serve
php vendor/bin/testbench db:seed --class="Workbench\\Database\\Seeders\\DatabaseSeeder"
```

The workbench registers 50 modules, the default role chain, a super admin (`admin@example.com` / `password`), and a manager who can approve an invoice only for their own pending records up to 50,000.

## Tests

```bash
composer test
composer analyse
composer format
```

## License

MIT. Copyright Imran Ahmed. Version 1.1.0.
