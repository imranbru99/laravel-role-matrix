<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use Illuminate\Support\Facades\Hash;
use ImranDevBD\RoleMatrix\Contracts\RoleServiceContract;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Services\AssignmentService;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

final class CreateSuperAdminCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:create-super-admin
        {--name= : Display name}
        {--email= : Email address}
        {--password= : Password}
        {--guard= : Guard name}
        {--json : Machine-readable output}';

    protected $description = 'Create a user and assign the protected Super Admin role';

    public function handle(RoleServiceContract $roles, AssignmentService $assignments): int
    {
        $guard = is_string($this->option('guard')) && $this->option('guard') !== ''
            ? (string) $this->option('guard')
            : RoleMatrixConfig::defaultGuard();

        $roles->seedDefaults($guard);

        $name = $this->stringOption('name') ?? ($this->input->isInteractive() ? text('Name', default: 'Super Admin') : null);
        $email = $this->stringOption('email') ?? ($this->input->isInteractive() ? text('Email', required: true) : null);
        $password = $this->stringOption('password') ?? ($this->input->isInteractive() ? password('Password', required: true) : null);

        if (! is_string($email) || ! is_string($password) || $email === '' || $password === '') {
            $this->components->error('Email and password are required. Pass --email and --password with --no-interaction.');

            return self::FAILURE;
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = RoleMatrixConfig::model('user');
        $user = $userModel::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name ?: 'Super Admin',
            'email' => $email,
            'password' => Hash::make($password),
        ])->save();

        $role = Role::query()->forGuard($guard)->where('is_super_admin', true)->firstOrFail();
        $assignments->assign($user, [$role], null, $guard);

        $payload = ['email' => $email, 'role' => $role->slug, 'guard' => $guard];

        if ($this->wantsJson()) {
            return $this->emit($payload);
        }

        $this->components->info("Super admin {$email} is ready.");

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
