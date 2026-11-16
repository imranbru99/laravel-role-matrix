<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Registry;

use Illuminate\Support\Str;
use ImranDevBD\RoleMatrix\Contracts\ModuleContract;
use ImranDevBD\RoleMatrix\Exceptions\CircularDependencyException;
use ImranDevBD\RoleMatrix\Exceptions\InvalidModuleException;
use ImranDevBD\RoleMatrix\Support\PermissionKey;

final class ModuleRegistry
{
    /** @var array<string, Module> */
    private array $modules = [];

    /** @var list<callable(self): void> */
    private array $extenders = [];

    private bool $discovered = false;

    public function module(string $slug, bool $overwrite = false): Module
    {
        PermissionKey::assertModule($slug);
        $slug = strtolower($slug);

        if (isset($this->modules[$slug])) {
            if (! $overwrite) {
                throw new InvalidModuleException("Module [{$slug}] is already registered.");
            }

            return $this->modules[$slug];
        }

        $module = new Module($slug);
        $module->setCycleChecker(fn () => $this->assertAcyclic());
        $this->modules[$slug] = $module;

        return $module;
    }

    public function has(string $slug): bool
    {
        return isset($this->modules[strtolower($slug)]);
    }

    public function find(string $slug): Module
    {
        $slug = strtolower($slug);

        if (! isset($this->modules[$slug])) {
            throw new InvalidModuleException("Module [{$slug}] is not registered.");
        }

        return $this->modules[$slug];
    }

    public function forget(string $slug): void
    {
        unset($this->modules[strtolower($slug)]);
    }

    public function extend(callable $callback): void
    {
        $this->extenders[] = $callback;
    }

    /**
     * @return list<Module>
     */
    public function all(): array
    {
        return array_values($this->modules);
    }

    /**
     * @return list<Module>
     */
    public function visible(?string $guard = null): array
    {
        $modules = array_values(array_filter(
            $this->modules,
            function (Module $module) use ($guard): bool {
                if ($module->isHidden()) {
                    return false;
                }

                return $guard === null || $module->appliesToGuard($guard);
            },
        ));

        usort($modules, fn (Module $a, Module $b): int => $a->getSort() <=> $b->getSort() ?: strcmp($a->getLabel(), $b->getLabel()));

        return $modules;
    }

    /**
     * @return list<ModuleGroup>
     */
    public function grouped(?string $guard = null): array
    {
        $groups = [];

        foreach ($this->visible($guard) as $module) {
            $name = $module->rawGroup();
            $groups[$name] ??= [
                'label' => $module->getGroup(),
                'modules' => [],
                'sort' => $module->getSort(),
            ];
            $groups[$name]['modules'][] = $module;
            $groups[$name]['sort'] = min($groups[$name]['sort'], $module->getSort());
        }

        uasort($groups, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_map(
            fn (string $name, array $group): ModuleGroup => new ModuleGroup($name, $group['label'], $group['modules'], $group['sort']),
            array_keys($groups),
            array_values($groups),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public function requirements(?string $guard = null): array
    {
        $map = [];

        foreach ($this->visible($guard) as $module) {
            $map = array_merge($map, $module->requirements());
        }

        $this->assertRequirementCycles($map);

        return $map;
    }

    /**
     * @param  array<string, list<string>>  $requires
     * @return array<string, list<string>>
     */
    public function requiredBy(array $requires): array
    {
        $reverse = [];

        foreach ($requires as $permission => $dependencies) {
            foreach ($dependencies as $dependency) {
                $reverse[$dependency] ??= [];
                $reverse[$dependency][] = $permission;
            }
        }

        return $reverse;
    }

    public function assertAcyclic(): void
    {
        $graph = [];

        foreach ($this->modules as $slug => $module) {
            $graph[$slug] ??= [];

            foreach ($module->dependencies() as $dependency) {
                $graph[$slug][] = $dependency['module'];
            }
        }

        $this->detectCycles($graph, 'module');
        $this->assertRequirementCycles($this->rawRequirements());
    }

    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;
        $this->loadCompiledCache();

        foreach ($this->extenders as $callback) {
            $callback($this);
        }

        $paths = config('role-matrix.modules.paths', []);
        $namespace = trim((string) config('role-matrix.modules.namespace', 'App\\RoleMatrix\\Modules'), '\\');

        foreach (is_array($paths) ? $paths : [] as $path) {
            if (! is_string($path) || ! is_dir($path)) {
                continue;
            }

            foreach (glob(rtrim($path, '/').'/*.php') ?: [] as $file) {
                $class = $namespace.'\\'.pathinfo($file, PATHINFO_FILENAME);

                if (! class_exists($class)) {
                    require_once $file;
                }

                if (! class_exists($class) || ! is_subclass_of($class, ModuleContract::class)) {
                    continue;
                }

                $slug = method_exists($class, 'slug')
                    ? strtolower((string) $class::slug())
                    : Str::kebab(str_replace('Module', '', class_basename($class)));

                if ($this->has($slug)) {
                    continue;
                }

                $module = $this->module($slug);
                $module->definitionClass($class);
                /** @var ModuleContract $definition */
                $definition = new $class;
                $definition->configure($module);
            }
        }

        if (app()->bound('role-matrix.modules')) {
            // Tagged services are resolved below when the container can see the tag.
        }

        if (method_exists(app(), 'tagged')) {
            foreach (app()->tagged('role-matrix.modules') as $definition) {
                if (! $definition instanceof ModuleContract) {
                    continue;
                }

                $class = $definition::class;
                $slug = Str::kebab(str_replace('Module', '', class_basename($class)));

                if ($this->has($slug)) {
                    continue;
                }

                $module = $this->module($slug);
                $module->definitionClass($class);
                $definition->configure($module);
            }
        }

        $this->assertAcyclic();
    }

    public function flush(): void
    {
        $this->modules = [];
        $this->discovered = false;
    }

    public function writeCompiledCache(?string $path = null): void
    {
        $path ??= (string) config('role-matrix.modules.cache_path');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $payload = array_map(fn (Module $module): array => $module->toCacheArray(), $this->modules);
        $export = var_export($payload, true);
        file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn {$export};\n");
    }

    public function forgetCompiledCache(): void
    {
        $path = (string) config('role-matrix.modules.cache_path');

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @return list<string>
     */
    public function permissionNames(?string $guard = null): array
    {
        $names = [];

        foreach ($this->visible($guard) as $module) {
            foreach ($module->actionsMap() as $action) {
                $names[] = $action->key($module->slug());
            }
        }

        return array_values(array_unique($names));
    }

    private function loadCompiledCache(): void
    {
        $path = (string) config('role-matrix.modules.cache_path');

        if (! is_file($path)) {
            return;
        }

        /** @var mixed $payload */
        $payload = require $path;

        if (! is_array($payload)) {
            return;
        }

        foreach ($payload as $data) {
            if (! is_array($data) || ! isset($data['slug']) || $this->has((string) $data['slug'])) {
                continue;
            }

            $module = Module::fromCacheArray($data);
            $module->setCycleChecker(fn () => $this->assertAcyclic());
            $this->modules[$module->slug()] = $module;
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function rawRequirements(): array
    {
        $map = [];

        foreach ($this->modules as $module) {
            if ($module->isHidden()) {
                continue;
            }

            $map = array_merge($map, $module->requirements());
        }

        return $map;
    }

    /**
     * @param  array<string, list<string>>  $requires
     */
    private function assertRequirementCycles(array $requires): void
    {
        foreach (array_keys($requires) as $start) {
            $seen = [];
            $stack = $requires[$start] ?? [];

            while ($stack !== []) {
                $node = array_pop($stack);

                if ($node === $start) {
                    throw new CircularDependencyException("Cyclic permission dependency involving [{$start}].");
                }

                if (isset($seen[$node])) {
                    continue;
                }

                $seen[$node] = true;

                foreach ($requires[$node] ?? [] as $next) {
                    $stack[] = $next;
                }
            }
        }
    }

    /**
     * @param  array<string, list<string>>  $graph
     */
    private function detectCycles(array $graph, string $noun): void
    {
        $visiting = [];
        $visited = [];

        $visit = function (string $node) use (&$visit, &$visiting, &$visited, $graph, $noun): void {
            if (isset($visited[$node])) {
                return;
            }

            if (isset($visiting[$node])) {
                throw new CircularDependencyException("Cyclic {$noun} dependency involving [{$node}].");
            }

            $visiting[$node] = true;

            foreach ($graph[$node] ?? [] as $next) {
                $visit($next);
            }

            unset($visiting[$node]);
            $visited[$node] = true;
        };

        foreach (array_keys($graph) as $node) {
            $visit($node);
        }
    }
}
