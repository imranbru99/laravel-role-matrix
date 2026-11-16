<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use ImranDevBD\RoleMatrix\Contracts\RoleServiceContract;
use ImranDevBD\RoleMatrix\Contracts\SyncServiceContract;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Services\AssignmentService;
use Workbench\App\Models\User;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(RoleServiceContract::class)->seedDefaults('web');
        app(SyncServiceContract::class)->sync('web');

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password')],
        );
        $manager = User::query()->firstOrCreate(
            ['email' => 'manager@example.com'],
            ['name' => 'Mina Manager', 'password' => Hash::make('password')],
        );

        $super = Role::query()->where('slug', 'super-admin')->first();
        $managerRole = Role::query()->where('slug', 'manager')->first();

        if ($super instanceof Role) {
            $admin->syncRoles($super);
        }

        if ($managerRole instanceof Role) {
            $manager->syncRoles($managerRole);
            app(AssignmentService::class)->applyRoleDiff($managerRole, ['invoices.approve'], [], [
                'invoices.approve' => [
                    'all' => [
                        ['rule' => 'ownership', 'owner_key' => 'user_id'],
                        ['rule' => 'status', 'field' => 'status', 'values' => 'pending'],
                        ['rule' => 'amount', 'field' => 'total', 'operator' => 'lte', 'value' => 50000, 'currency' => 'BDT'],
                    ],
                ],
            ]);
        }
    }
}
