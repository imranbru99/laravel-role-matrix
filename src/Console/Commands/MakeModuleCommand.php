<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class MakeModuleCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:make-module
        {name : Module slug, such as inventory}
        {--preset=crud : crud, readOnly, workflow, or reportable}
        {--group=General : Matrix group}
        {--force : Overwrite an existing file}
        {--json : Machine-readable output}';

    protected $description = 'Scaffold a module definition class';

    public function handle(): int
    {
        $slug = Str::kebab((string) $this->argument('name'));
        $class = Str::studly(str_replace('-', ' ', $slug)).'Module';
        $directory = app_path('RoleMatrix/Modules');
        $path = $directory.'/'.$class.'.php';

        if (is_file($path) && ! $this->option('force')) {
            $this->components->error("{$path} already exists. Pass --force to overwrite.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists($directory);
        $preset = (string) $this->option('preset');
        $group = (string) $this->option('group');
        $method = match ($preset) {
            'readOnly', 'readonly' => 'readOnly',
            'workflow' => 'workflow',
            'reportable' => 'reportable',
            default => 'crud',
        };

        File::put($path, <<<PHP
<?php

declare(strict_types=1);

namespace App\\RoleMatrix\\Modules;

use ImranDevBD\\RoleMatrix\\Contracts\\ModuleContract;
use ImranDevBD\\RoleMatrix\\Registry\\Module;

final class {$class} implements ModuleContract
{
    public static function slug(): string
    {
        return '{$slug}';
    }

    public function configure(Module \$module): void
    {
        \$module
            ->label('{$this->label($slug)}')
            ->group('{$group}')
            ->{$method}();
    }
}

PHP);

        if ($this->wantsJson()) {
            return $this->emit(['path' => $path, 'slug' => $slug]);
        }

        $this->components->info("Module written to {$path}");

        return self::SUCCESS;
    }

    private function label(string $slug): string
    {
        return Str::headline($slug);
    }
}
