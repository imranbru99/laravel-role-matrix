<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;
use ImranDevBD\RoleMatrix\Support\SchemaKey;

return new class extends Migration
{
    public function up(): void
    {
        $roles = RoleMatrixConfig::table('roles');
        $permissions = RoleMatrixConfig::table('permissions');
        $rolePermissions = RoleMatrixConfig::table('role_has_permissions');
        $modelRoles = RoleMatrixConfig::table('model_has_roles');
        $modelPermissions = RoleMatrixConfig::table('model_has_permissions');
        $audit = RoleMatrixConfig::table('audit_logs');

        if (! Schema::hasTable($roles)) {
            Schema::create($roles, function (Blueprint $table): void {
                SchemaKey::id($table);
                $table->string('name');
                $table->string('slug');
                $table->string('guard_name')->default('web');
                $table->text('description')->nullable();
                $table->string('color', 7)->nullable();
                $table->string('icon')->nullable();
                SchemaKey::foreignColumn($table, 'parent_id', true);
                $table->unsignedInteger('level')->default(0);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_super_admin')->default(false);
                $table->boolean('is_default')->default(false);
                $table->string('status', 20)->default('active');
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['name', 'guard_name'], 'rm_roles_name_guard_uq');
                $table->unique(['slug', 'guard_name'], 'rm_roles_slug_guard_uq');
                $table->index(['guard_name', 'status'], 'rm_roles_guard_status_idx');
                $table->index('parent_id', 'rm_roles_parent_idx');
                $table->index('is_super_admin', 'rm_roles_super_idx');
            });

            Schema::table($roles, function (Blueprint $table) use ($roles): void {
                $table->foreign('parent_id', 'rm_roles_parent_fk')
                    ->references('id')
                    ->on($roles)
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable($permissions)) {
            Schema::create($permissions, function (Blueprint $table): void {
                SchemaKey::id($table);
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->string('module_slug')->nullable();
                $table->string('action')->nullable();
                $table->string('group')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['name', 'guard_name'], 'rm_permissions_name_guard_uq');
                $table->index(['guard_name', 'module_slug'], 'rm_permissions_module_idx');
                $table->index('action', 'rm_permissions_action_idx');
            });
        }

        if (! Schema::hasTable($rolePermissions)) {
            Schema::create($rolePermissions, function (Blueprint $table) use ($roles, $permissions): void {
                SchemaKey::id($table);
                SchemaKey::foreignColumn($table, 'role_id');
                SchemaKey::foreignColumn($table, 'permission_id');
                $table->json('conditions')->nullable();
                $table->timestamp('granted_at')->nullable();
                $table->string('granted_by')->nullable();
                $table->timestamps();

                $table->unique(['role_id', 'permission_id'], 'rm_role_perm_uq');
                $table->index('permission_id', 'rm_role_perm_permission_idx');

                $table->foreign('role_id', 'rm_rhp_role_fk')->references('id')->on($roles)->cascadeOnDelete();
                $table->foreign('permission_id', 'rm_rhp_permission_fk')->references('id')->on($permissions)->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable($modelRoles)) {
            Schema::create($modelRoles, function (Blueprint $table) use ($roles): void {
                SchemaKey::id($table);
                SchemaKey::foreignColumn($table, 'role_id');
                $table->string('model_type');
                $table->string('model_id');
                $table->string('team_id')->nullable();
                $table->string('scope_key')->default('');
                $table->timestamp('expires_at')->nullable();
                $table->string('assigned_by')->nullable();
                $table->timestamps();

                $table->unique(['role_id', 'model_type', 'model_id', 'scope_key'], 'rm_model_roles_uq');
                $table->index(['model_type', 'model_id'], 'rm_model_roles_model_idx');
                $table->index('expires_at', 'rm_model_roles_expires_idx');
                $table->index('team_id', 'rm_model_roles_team_idx');

                $table->foreign('role_id', 'rm_mhr_role_fk')->references('id')->on($roles)->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable($modelPermissions)) {
            Schema::create($modelPermissions, function (Blueprint $table) use ($permissions): void {
                SchemaKey::id($table);
                SchemaKey::foreignColumn($table, 'permission_id');
                $table->string('model_type');
                $table->string('model_id');
                $table->string('effect', 10)->default('allow');
                $table->json('conditions')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('team_id')->nullable();
                $table->string('scope_key')->default('');
                $table->string('assigned_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['permission_id', 'model_type', 'model_id', 'scope_key', 'effect'],
                    'rm_model_perms_uq',
                );
                $table->index(['model_type', 'model_id'], 'rm_model_perms_model_idx');
                $table->index('expires_at', 'rm_model_perms_expires_idx');

                $table->foreign('permission_id', 'rm_mhp_permission_fk')->references('id')->on($permissions)->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable($audit)) {
            Schema::create($audit, function (Blueprint $table): void {
                SchemaKey::id($table);
                $table->string('event');
                $table->string('auditable_type')->nullable();
                $table->string('auditable_id')->nullable();
                $table->string('actor_type')->nullable();
                $table->string('actor_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->uuid('batch_uuid')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['auditable_type', 'auditable_id', 'created_at'], 'rm_audit_auditable_idx');
                $table->index(['actor_type', 'actor_id'], 'rm_audit_actor_idx');
                $table->index('event', 'rm_audit_event_idx');
                $table->index('batch_uuid', 'rm_audit_batch_idx');
                $table->index('created_at', 'rm_audit_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(RoleMatrixConfig::table('model_has_permissions'));
        Schema::dropIfExists(RoleMatrixConfig::table('model_has_roles'));
        Schema::dropIfExists(RoleMatrixConfig::table('role_has_permissions'));
        Schema::dropIfExists(RoleMatrixConfig::table('audit_logs'));
        Schema::dropIfExists(RoleMatrixConfig::table('permissions'));
        Schema::dropIfExists(RoleMatrixConfig::table('roles'));
    }
};
