<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Database\Seeders;

use Illuminate\Database\Seeder;
use ImranDevBD\RoleMatrix\Services\RoleService;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

class RoleMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $guard = RoleMatrixConfig::defaultGuard();

        foreach (RoleMatrixConfig::guards() as $name) {
            app(RoleService::class)->seedDefaults($name);
        }

        if (! in_array($guard, RoleMatrixConfig::guards(), true)) {
            app(RoleService::class)->seedDefaults($guard);
        }
    }
}
