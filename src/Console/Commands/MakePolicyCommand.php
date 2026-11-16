<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class MakePolicyCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:make-policy
        {model : Eloquent model class base name}
        {module : Module slug}
        {--force : Overwrite an existing policy}
        {--json : Machine-readable output}';

    protected $description = 'Generate a policy that authorizes through the matrix';

    public function handle(): int
    {
        $model = Str::studly((string) $this->argument('model'));
        $module = Str::kebab((string) $this->argument('module'));
        $directory = app_path('Policies');
        $path = $directory.'/'.$model.'Policy.php';

        if (is_file($path) && ! $this->option('force')) {
            $this->components->error("{$path} already exists.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists($directory);
        File::put($path, <<<PHP
<?php

declare(strict_types=1);

namespace App\\Policies;

use ImranDevBD\\RoleMatrix\\Policies\\BasePolicy;

final class {$model}Policy extends BasePolicy
{
    protected function module(): string
    {
        return '{$module}';
    }
}

PHP);

        if ($this->wantsJson()) {
            return $this->emit(['path' => $path, 'module' => $module]);
        }

        $this->components->info("Policy written to {$path}");
        $this->line('Register it with Gate::policy(App\\Models\\'.$model.'::class, App\\Policies\\'.$model.'Policy::class);');

        return self::SUCCESS;
    }
}
