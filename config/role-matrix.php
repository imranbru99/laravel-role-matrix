<?php

declare(strict_types=1);

use ImranDevBD\RoleMatrix\Conditions\AmountCondition;
use ImranDevBD\RoleMatrix\Conditions\AttributeCondition;
use ImranDevBD\RoleMatrix\Conditions\DepartmentCondition;
use ImranDevBD\RoleMatrix\Conditions\IpRangeCondition;
use ImranDevBD\RoleMatrix\Conditions\OwnershipCondition;
use ImranDevBD\RoleMatrix\Conditions\StatusCondition;
use ImranDevBD\RoleMatrix\Conditions\TeamCondition;
use ImranDevBD\RoleMatrix\Conditions\TimeWindowCondition;
use ImranDevBD\RoleMatrix\Models\AuditLog;
use ImranDevBD\RoleMatrix\Models\ModelPermission;
use ImranDevBD\RoleMatrix\Models\ModelRole;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Models\RolePermission;
use ImranDevBD\RoleMatrix\Services\AuditService;
use ImranDevBD\RoleMatrix\Services\CacheService;
use ImranDevBD\RoleMatrix\Services\CloneService;
use ImranDevBD\RoleMatrix\Services\ImportExportService;
use ImranDevBD\RoleMatrix\Services\PermissionResolver;
use ImranDevBD\RoleMatrix\Services\RoleService;
use ImranDevBD\RoleMatrix\Services\SyncService;

return [

    /*
    |--------------------------------------------------------------------------
    | Engine
    |--------------------------------------------------------------------------
    |
    | "native" stores and evaluates everything in Role Matrix tables.
    | "spatie" keeps that behaviour and also mirrors flat role/permission
    | names into spatie/laravel-permission. Hierarchy, ABAC, expiry, teams,
    | and audit stay on this package either way. Prefix the tables below
    | before turning the bridge on if Spatie is already installed.
    |
    */

    'engine' => env('ROLE_MATRIX_ENGINE', 'native'),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap any of these for your own subclasses. The user model is the one
    | that should use ImranDevBD\RoleMatrix\Traits\HasRoleMatrix.
    |
    */

    'models' => [
        'role' => Role::class,
        'permission' => Permission::class,
        'audit' => AuditLog::class,
        'role_permission' => RolePermission::class,
        'model_role' => ModelRole::class,
        'model_permission' => ModelPermission::class,
        'user' => env('ROLE_MATRIX_USER_MODEL', 'App\\Models\\User'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    'services' => [
        'resolver' => PermissionResolver::class,
        'roles' => RoleService::class,
        'sync' => SyncService::class,
        'clone' => CloneService::class,
        'import_export' => ImportExportService::class,
        'audit' => AuditService::class,
        'cache' => CacheService::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Names are prefixed with `prefix` (for example "rm_"). Leave the prefix
    | empty to use the canonical names. Set ROLE_MATRIX_TABLE_PREFIX=rm_
    | when spatie/laravel-permission already owns roles/permissions.
    |
    */

    'table_names' => [
        'prefix' => env('ROLE_MATRIX_TABLE_PREFIX', ''),
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_has_permissions' => 'role_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'model_has_permissions' => 'model_has_permissions',
        'audit_logs' => 'role_matrix_audit_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Primary key type
    |--------------------------------------------------------------------------
    |
    | int | ulid | uuid. Choose this before the first migration. Morph ids are
    | stored as strings so user keys of any type fit.
    |
    */

    'key_type' => env('ROLE_MATRIX_KEY_TYPE', 'int'),

    /*
    |--------------------------------------------------------------------------
    | Load migrations from the package
    |--------------------------------------------------------------------------
    */

    'load_migrations' => env('ROLE_MATRIX_LOAD_MIGRATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        'default' => env('ROLE_MATRIX_GUARD', 'web'),
        'allowed' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ROLE_MATRIX_GUARDS', 'web,api')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | intercept:
    |   all         — Gate::before returns true for every ability
    |   permissions — only dotted matrix keys such as inventory.update
    |
    | bypass_ability is a Gate ability you may define to let a break-glass
    | operator edit the protected role. Leave it null to disable the bypass.
    |
    */

    'super_admin' => [
        'name' => env('ROLE_MATRIX_SUPER_ADMIN', 'Super Admin'),
        'slug' => env('ROLE_MATRIX_SUPER_ADMIN_SLUG', 'super-admin'),
        'intercept' => env('ROLE_MATRIX_SUPER_INTERCEPT', 'all'),
        'bypass_ability' => env('ROLE_MATRIX_SUPER_BYPASS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | store null uses the application default. Tags are used when the store
    | supports them; a versioned key is always used so file/array/database
    | drivers still invalidate.
    |
    */

    'cache' => [
        'store' => env('ROLE_MATRIX_CACHE_STORE'),
        'ttl' => (int) env('ROLE_MATRIX_CACHE_TTL', 3600),
        'prefix' => env('ROLE_MATRIX_CACHE_PREFIX', 'role-matrix'),
        'tags' => env('ROLE_MATRIX_CACHE_TAGS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hierarchy
    |--------------------------------------------------------------------------
    */

    'hierarchy' => [
        'enabled' => env('ROLE_MATRIX_HIERARCHY', true),
        'max_depth' => (int) env('ROLE_MATRIX_HIERARCHY_DEPTH', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wildcards
    |--------------------------------------------------------------------------
    */

    'wildcards' => [
        'enabled' => env('ROLE_MATRIX_WILDCARDS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Direct user permissions
    |--------------------------------------------------------------------------
    */

    'direct_permissions' => [
        'enabled' => env('ROLE_MATRIX_DIRECT_PERMISSIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary roles
    |--------------------------------------------------------------------------
    */

    'temporary_roles' => [
        'enabled' => env('ROLE_MATRIX_TEMPORARY_ROLES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Teams
    |--------------------------------------------------------------------------
    |
    | When enabled, assignments with an empty scope are global and assignments
    | with a team id apply only while that team is the current context.
    | Set the current team with RoleMatrix::setTeam() or the
    | `role-matrix.team` middleware.
    |
    */

    'teams' => [
        'enabled' => env('ROLE_MATRIX_TEAMS', false),
        'foreign_key' => env('ROLE_MATRIX_TEAM_KEY', 'team_id'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ABAC
    |--------------------------------------------------------------------------
    |
    | strict=false: a conditional grant allows class-level checks (menus)
    | when no record is passed. strict=true fails those checks closed.
    | scopeAccessibleBy() still filters lists in the database.
    |
    */

    'abac' => [
        'enabled' => env('ROLE_MATRIX_ABAC', true),
        'strict' => env('ROLE_MATRIX_ABAC_STRICT', false),
        'owner_key' => env('ROLE_MATRIX_OWNER_KEY', 'user_id'),
        'rules' => [
            OwnershipCondition::class,
            TeamCondition::class,
            DepartmentCondition::class,
            StatusCondition::class,
            AmountCondition::class,
            TimeWindowCondition::class,
            IpRangeCondition::class,
            AttributeCondition::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    */

    'modules' => [
        'paths' => [
            app_path('RoleMatrix/Modules'),
        ],
        'namespace' => 'App\\RoleMatrix\\Modules',
        'auto_sync_on_boot' => env('ROLE_MATRIX_AUTO_SYNC', false),
        'cache_path' => storage_path('framework/cache/role-matrix.php'),
        'dangerous_actions' => ['delete', 'force_delete', 'approve', 'reject', 'destroy', 'manage'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    |
    | driver: db | log | stack (db and log) | null (off, same as enabled=false)
    |
    */

    'audit' => [
        'enabled' => env('ROLE_MATRIX_AUDIT', true),
        'driver' => env('ROLE_MATRIX_AUDIT_DRIVER', 'db'),
        'retention_days' => (int) env('ROLE_MATRIX_AUDIT_RETENTION', 90),
        'queue' => env('ROLE_MATRIX_AUDIT_QUEUE', false),
        'prune_daily' => env('ROLE_MATRIX_AUDIT_PRUNE_DAILY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament
    |--------------------------------------------------------------------------
    */

    'filament' => [
        'navigation_group' => 'Access Control',
        'navigation_icon' => 'heroicon-o-table-cells',
        'navigation_sort' => 20,
        'role_slug' => 'roles',
        'matrix_slug' => 'role-matrix',
        'audit_slug' => 'role-matrix-audit',
        'import_slug' => 'role-matrix-import',
        'debugger_slug' => 'role-matrix-debugger',
        'enable_audit' => true,
        'enable_import' => true,
        'enable_debugger' => true,
        'default_view' => 'columns',
        'compact' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Standalone UI (non-Filament)
    |--------------------------------------------------------------------------
    */

    'standalone_ui' => [
        'enabled' => env('ROLE_MATRIX_STANDALONE', false),
        'prefix' => env('ROLE_MATRIX_STANDALONE_PREFIX', 'role-matrix'),
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON API
    |--------------------------------------------------------------------------
    */

    'api' => [
        'enabled' => env('ROLE_MATRIX_API', false),
        'prefix' => env('ROLE_MATRIX_API_PREFIX', 'api/role-matrix'),
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | mail_to and slack_webhook opt in to the bundled listeners. Both are
    | off until you set them. Listeners are queued when queue=true.
    |
    */

    'events' => [
        'enabled' => true,
        'queue' => env('ROLE_MATRIX_EVENT_QUEUE', false),
        'mail_to' => env('ROLE_MATRIX_NOTIFY_MAIL'),
        'slack_webhook' => env('ROLE_MATRIX_SLACK_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | guard_mode: default | explicit | sanctum
    | Aliases registered by the provider: role, permission, role_or_permission,
    | matrix, role-matrix.team.
    |
    */

    'middleware' => [
        'guard_mode' => env('ROLE_MATRIX_GUARD_MODE', 'default'),
        'register_aliases' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    'localization' => [
        'locale' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    */

    'ui' => [
        'brand' => env('ROLE_MATRIX_BRAND', '#4f46e5'),
        'density' => env('ROLE_MATRIX_DENSITY', 'comfortable'),
        'per_page' => (int) env('ROLE_MATRIX_PER_PAGE', 25),
        'danger_user_threshold' => (int) env('ROLE_MATRIX_DANGER_USERS', 10),
        'virtualize_after' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate
    |--------------------------------------------------------------------------
    */

    'gate' => [
        'enabled' => env('ROLE_MATRIX_GATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Import / export
    |--------------------------------------------------------------------------
    */

    'schema' => 'role-matrix/1',

    /*
    |--------------------------------------------------------------------------
    | Spatie bridge
    |--------------------------------------------------------------------------
    */

    'spatie' => [
        'enabled' => env('ROLE_MATRIX_ENGINE', 'native') === 'spatie',
        'permission_config' => 'permission',
    ],

];
