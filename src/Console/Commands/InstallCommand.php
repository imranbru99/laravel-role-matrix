<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use Illuminate\Support\Facades\File;
use ImranDevBD\RoleMatrix\Contracts\RoleServiceContract;
use ImranDevBD\RoleMatrix\Contracts\SyncServiceContract;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

final class InstallCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:install
        {--migrate : Run the migrations without asking}
        {--no-migrate : Skip migrations}
        {--seed : Create the default roles}
        {--patch-user : Add HasRoleMatrix to the user model without asking}
        {--json : Machine-readable output}';

    protected $description = 'Publish Role Matrix assets, migrate, and prepare default roles';

    public function handle(RoleServiceContract $roles, SyncServiceContract $sync): int
    {
        $this->callSilent('vendor:publish', ['--tag' => 'role-matrix-config', '--force' => false]);
        $this->callSilent('vendor:publish', ['--tag' => 'role-matrix-lang']);
        $this->callSilent('vendor:publish', ['--tag' => 'role-matrix-views']);

        $migrate = $this->option('migrate') || (! $this->option('no-migrate') && ($this->wantsJson() || ! $this->input->isInteractive() || confirm('Run the migrations now?', true)));

        if ($migrate) {
            $this->call('migrate');
        }

        if ($this->option('seed') || (! $this->wantsJson() && $this->input->isInteractive() && confirm('Create Viewer, Staff, Manager, Admin, and Super Admin?', true))) {
            foreach (RoleMatrixConfig::guards() as $guard) {
                $roles->seedDefaults($guard);
            }
            $sync->sync();
        }

        $patched = false;

        if ($this->option('patch-user') || (! $this->wantsJson() && $this->input->isInteractive() && confirm('Add the HasRoleMatrix trait to the user model?', false))) {
            $patched = $this->patchUser();
        }

        if ($this->input->isInteractive() && ! $this->wantsJson() && confirm('Create a super admin user now?', false)) {
            $this->call('role-matrix:create-super-admin', [
                '--name' => text('Name', default: 'Super Admin'),
                '--email' => text('Email', required: true),
                '--password' => password('Password', required: true),
            ]);
        }

        $filament = class_exists(\Filament\Panel::class);
        $result = [
            'migrated' => (bool) $migrate,
            'patched_user' => $patched,
            'filament' => $filament,
            'roles' => Role::query()->count(),
        ];

        if ($this->wantsJson()) {
            return $this->emit($result);
        }

        $this->components->info('Role Matrix is installed.');

        if ($filament) {
            $this->line('Register the plugin on your panel:');
            $this->line(' ->plugin(\\ImranDevBD\\RoleMatrix\\Filament\\RoleMatrixPlugin::make())');
        } else {
            $this->line('Filament is not installed. Enable standalone UI with ROLE_MATRIX_STANDALONE=true or add filament/filament.');
        }

        $this->line('Add `use ImranDevBD\\RoleMatrix\\Traits\\HasRoleMatrix;` to '.config('role-matrix.models.user').'.');

        return self::SUCCESS;
    }

    private function patchUser(): bool
    {
        $class = (string) config('role-matrix.models.user', 'App\\Models\\User');
        $path = app_path('Models/'.class_basename($class).'.php');

        if (! is_file($path)) {
            $this->components->warn("Could not find {$path}. Add the trait yourself.");

            return false;
        }

        $contents = File::get($path);

        if (str_contains($contents, 'HasRoleMatrix')) {
            return true;
        }

        $updated = preg_replace(
            '/(namespace [^;]+;\s+)/',
            "$1\nuse ImranDevBD\\RoleMatrix\\Traits\\HasRoleMatrix;\n",
            $contents,
            1,
        );
        $updated = preg_replace(
            '/(class\s+\w+[^{]*\{)/',
            "$1\n    use HasRoleMatrix;\n",
            (string) $updated,
            1,
        );

        if (! is_string($updated) || ! str_contains($updated, 'HasRoleMatrix')) {
            $this->components->warn('Could not patch the user model automatically.');

            return false;
        }

        File::put($path, $updated);
        $this->components->info('Added HasRoleMatrix to '.$path);

        return true;
    }
}
