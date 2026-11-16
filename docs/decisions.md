# Architecture decisions

These choices were made while building v1.0.0 so the package could stay conventional without stopping on every ambiguity.

## Native engine is the source of truth

`engine=native` (default) reads and writes only Role Matrix tables. `engine=spatie` still uses those tables for hierarchy, ABAC, expiry, teams, and audit, then mirrors flat role and permission names into `spatie/laravel-permission`. Do not put both `HasRoleMatrix` and Spatie's `HasRoles` on the same model. Spatie middleware cannot see conditions, expiry, or inheritance.

If Spatie is installed and the configured table names collide with Spatie's (`roles`, `permissions`, `model_has_roles`, …), the bridge refuses to boot. Set `ROLE_MATRIX_TABLE_PREFIX=rm_` first.

## Primary keys

Default key type is `int` (`bigIncrements`) because it matches Laravel 10–13 foreign keys with the least surprise. `ulid` and `uuid` are supported via `ROLE_MATRIX_KEY_TYPE`. Morph ids are strings so user keys of any of those types fit.

## Hierarchy direction

`parent_id` means "also grant everything this role grants". A child walks **up** `parent_id` and unions those permissions. The seeded chain is therefore Viewer ← Staff ← Manager ← Admin: each senior role inherits the junior role, then adds its own grants. Super Admin is **not** in that chain. A non-super role cannot use a super-admin role as an ancestor, otherwise every descendant would become omnipotent. Super Admin is a short-circuit flag, not a bag of inherited rows.

The marketing arrow "Super Admin → Admin → Manager → Staff → Viewer" is authority order (highest first), not the `parent_id` direction.

## Soft deletes and foreign keys

Roles use soft deletes. Pivot foreign keys use `cascadeOnDelete` so a **force** delete removes grants. A normal delete does not fire those keys, and assignments are kept until the admin reassigns or explicitly detaches. `parent_id` uses `nullOnDelete`.

Uniqueness for team-scoped pivots uses a real `scope_key` column (`''` when global). SQL `NULL` is not unique on MySQL, so a nullable `team_id` alone cannot enforce one row per user/role/team.

## Cache

Invalidation bumps a version integer in the cache key. That works on every store, including `file` and `array`, which do not support tags. When the store supports tags, the version tag is flushed too. Effective-permission objects are memoized for the current request/process and cleared on `RequestHandled` (and Octane `RequestReceived` when present) so workers never reuse another user's memo.

A cache outage falls through to the database. It must never fail a `can()` check closed.

## Gate hook

`Gate::before` only treats abilities that contain `.` or `*` as matrix permissions (`inventory.update`). Policy abilities (`update`, `viewAny`) keep working. An explicit direct **deny** returns `false`. An allow returns `true`. No matching grant returns `null` so policies can still allow.

Super Admin returns `true` for every ability when `super_admin.intercept=all` (default). Set it to `permissions` to only short-circuit dotted matrix keys.

## Conditional grants without a record

Menu visibility calls `can('invoices.approve')` with no model. Conditional grants count as allowed in that case so the item can render. `abac.strict=true` makes a missing record fail closed. Row lists should use `scopeAccessibleBy()`, which compiles ownership, team, department, status, and literal attribute rules into SQL.

## Module cache vs closures

`role-matrix:cache` writes a serializable snapshot. Closures such as `hidden(fn ())` cannot live in that file. On boot the snapshot fills slugs that are not already registered, then discovery and `RoleMatrix::module()` calls overlay it. Register dynamic visibility from a service provider `boot()` method so it runs every request.

## Migrations

The package loads its migrations by default (`load_migrations=true`) and the files are idempotent (`Schema::hasTable`). Publishing is for teams who want the files in `database/migrations`. Leave the flag on unless you remove the package copy from the migrator.

## Filament

Shared Blade, CSS, and the Alpine matrix are the UI. `src/Filament/V3` and `src/Filament/V4` only adapt signatures (`Form` vs `Schema`, navigation icon types, action schema methods). The plugin picks a version by checking for `Filament\Schemas\Schema`. PHPStan excludes those adapters because CI cannot install v3 and v4 in the same vendor directory. The engine is analysed at level 6.

## Why the matrix does not post per checkbox

The browser holds the grant map, dependency ticks, and the diff. Livewire receives one payload on Save, inside a transaction. That is what keeps 100 modules usable.
