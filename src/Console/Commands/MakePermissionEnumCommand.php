<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ImranDevBD\RoleMatrix\Registry\ModuleRegistry;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class MakePermissionEnumCommand extends RoleMatrixCommand
{
    protected $signature = 'role-matrix:make-permission-enum
        {--path= : File to write. Defaults to app/RoleMatrix/Permission.php}
        {--guard= : Limit the enum to one guard}
        {--force : Overwrite an existing file}
        {--json : Machine-readable output}';

    protected $description = 'Generate a backed enum of concrete permission keys';

    public function handle(ModuleRegistry $registry): int
    {
        $registry->discover();
        $guard = $this->option('guard');
        $guard = is_string($guard) && $guard !== '' ? $guard : RoleMatrixConfig::defaultGuard();
        $names = $registry->permissionNames($guard);
        sort($names);

        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? $path : app_path('RoleMatrix/Permission.php');

        if (is_file($path) && ! $this->option('force')) {
            $this->components->error("{$path} already exists. Pass --force to overwrite.");

            return self::FAILURE;
        }

        $cases = [];
        $used = [];

        foreach ($names as $name) {
            if (str_contains($name, '*')) {
                continue;
            }

            $case = $this->caseName($name, $used);
            $used[$case] = true;
            $cases[] = "    case {$case} = '{$name}';";
        }

        $body = $cases === [] ? '' : implode("\n", $cases)."\n";
        File::ensureDirectoryExists(dirname($path));
        File::put($path, <<<PHP
<?php

declare(strict_types=1);

namespace App\\RoleMatrix;

enum Permission: string
{
{$body}}

PHP);

        if ($this->wantsJson()) {
            return $this->emit(['path' => $path, 'count' => count($cases), 'guard' => $guard]);
        }

        $this->components->info('Wrote '.count($cases)." permissions to {$path}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, true>  $used
     */
    private function caseName(string $permission, array $used): string
    {
        $name = Str::studly(str_replace(['.', '-', '*'], ' ', $permission));

        if ($name === '' || is_numeric($name[0])) {
            $name = 'P'.$name;
        }

        $candidate = $name;
        $suffix = 2;

        while (isset($used[$candidate])) {
            $candidate = $name.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
