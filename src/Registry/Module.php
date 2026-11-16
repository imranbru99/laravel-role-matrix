<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Registry;

use Illuminate\Support\Str;
use ImranDevBD\RoleMatrix\Exceptions\InvalidModuleException;
use ImranDevBD\RoleMatrix\Support\PermissionKey;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class Module
{
    private string $label;

    private bool $labelExplicit = false;

    private ?string $icon = 'heroicon-o-squares-2x2';

    private string $group = 'General';

    private ?string $description = null;

    /** @var array<string, ModuleAction> */
    private array $actions = [];

    /**
     * @var list<array{module: string, action: string, when: list<string>|null}>
     */
    private array $dependencies = [];

    private int $sort = 0;

    /** @var bool|callable(): bool */
    private mixed $hidden = false;

    /** @var list<string> */
    private array $conditionable = [];

    /** @var list<string> */
    private array $guards = [];

    private ?string $definitionClass = null;

    private ?\Closure $cycleChecker = null;

    public function __construct(private readonly string $slug)
    {
        PermissionKey::assertModule($slug);
        $this->label = Str::headline($slug);
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        $this->labelExplicit = true;

        return $this;
    }

    public function getLabel(): string
    {
        if ($this->labelExplicit) {
            if (str_contains($this->label, '::') || str_starts_with($this->label, 'role-matrix.')) {
                return __($this->label);
            }

            return $this->label;
        }

        $key = 'role-matrix::modules.'.$this->slug.'.label';
        $translated = __($key);

        return $translated !== $key ? $translated : $this->label;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): string
    {
        if (str_contains($this->group, '::')) {
            return __($this->group);
        }

        return $this->group;
    }

    public function rawGroup(): string
    {
        return $this->group;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        if ($this->description !== null && str_contains($this->description, '::')) {
            return __($this->description);
        }

        $key = 'role-matrix::modules.'.$this->slug.'.description';
        $translated = __($key);

        if ($this->description === null && $translated !== $key) {
            return $translated;
        }

        return $this->description;
    }

    /**
     * @param  list<string>  $actions
     */
    public function actions(array $actions): static
    {
        foreach ($actions as $action) {
            $this->action($action);
        }

        return $this;
    }

    /**
     * @param  list<string>  $implies
     * @param  list<string>  $conditions
     */
    public function action(
        string $name,
        ?string $label = null,
        bool $danger = false,
        array $implies = [],
        array $conditions = [],
        ?string $description = null,
    ): static {
        PermissionKey::assertAction($name);
        $name = strtolower($name);

        if ($implies === [] && $name !== 'view') {
            $implies = ['view'];
        }

        if (! $danger && in_array($name, RoleMatrixConfig::dangerousActions(), true)) {
            $danger = true;
        }

        $this->actions[$name] = new ModuleAction(
            name: $name,
            label: $label ?? Str::headline($name),
            danger: $danger,
            description: $description,
            implies: array_values(array_unique(array_map(strtolower(...), $implies))),
            conditions: $conditions,
        );

        return $this;
    }

    public function crud(): static
    {
        return $this->preset('crud');
    }

    public function readOnly(): static
    {
        return $this->preset('readOnly');
    }

    public function workflow(): static
    {
        return $this->preset('workflow');
    }

    public function reportable(): static
    {
        return $this->preset('reportable');
    }

    public function preset(string $preset): static
    {
        $map = [
            'crud' => ['view', 'create', 'update', 'delete'],
            'readOnly' => ['view'],
            'readonly' => ['view'],
            'workflow' => ['view', 'create', 'update', 'delete', 'submit', 'approve', 'reject'],
            'reportable' => ['view', 'export', 'print'],
        ];

        if (! isset($map[$preset])) {
            throw new InvalidModuleException("Unknown module preset [{$preset}].");
        }

        return $this->actions($map[$preset]);
    }

    /**
     * @param  list<string>  $rules
     */
    public function conditionable(array $rules): static
    {
        $this->conditionable = array_values(array_unique(array_map(strtolower(...), $rules)));

        return $this;
    }

    /**
     * @return list<string>
     */
    public function conditionableRules(): array
    {
        return $this->conditionable;
    }

    /**
     * @param  string|list<string>  $modules
     * @param  list<string>|null  $when
     */
    public function dependsOn(string|array $modules, string $action = 'view', ?array $when = null): static
    {
        foreach ((array) $modules as $module) {
            PermissionKey::assertModule((string) $module);
            PermissionKey::assertAction($action);
            $this->dependencies[] = [
                'module' => strtolower((string) $module),
                'action' => strtolower($action),
                'when' => $when === null ? null : array_values(array_map(strtolower(...), $when)),
            ];
        }

        if ($this->cycleChecker instanceof \Closure) {
            ($this->cycleChecker)();
        }

        return $this;
    }

    /**
     * @return list<array{module: string, action: string, when: list<string>|null}>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function sort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function hidden(bool|callable $hidden = true): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function isHidden(): bool
    {
        if (is_bool($this->hidden)) {
            return $this->hidden;
        }

        return (bool) ($this->hidden)();
    }

    /**
     * @param  list<string>  $guards
     */
    public function guards(array $guards): static
    {
        $this->guards = array_values(array_map(strtolower(...), $guards));

        return $this;
    }

    /**
     * @return list<string>
     */
    public function guardNames(): array
    {
        return $this->guards;
    }

    public function appliesToGuard(string $guard): bool
    {
        return $this->guards === [] || in_array(strtolower($guard), $this->guards, true);
    }

    public function definitionClass(?string $class): static
    {
        $this->definitionClass = $class;

        return $this;
    }

    public function getDefinitionClass(): ?string
    {
        return $this->definitionClass;
    }

    public function setCycleChecker(callable $checker): void
    {
        $this->cycleChecker = $checker(...);
    }

    /**
     * @return array<string, ModuleAction>
     */
    public function actionsMap(): array
    {
        return $this->actions;
    }

    /**
     * Permission keys this module's actions require (same-module implies + dependsOn).
     *
     * @return array<string, list<string>>
     */
    public function requirements(): array
    {
        $map = [];

        foreach ($this->actions as $action) {
            $required = [];

            foreach ($action->implies as $implied) {
                if ($implied !== $action->name) {
                    $required[] = PermissionKey::make($this->slug, $implied);
                }
            }

            foreach ($this->dependencies as $dependency) {
                $when = $dependency['when'];
                $applies = $when === null ? $action->name !== 'view' : in_array($action->name, $when, true);

                if ($applies) {
                    $required[] = PermissionKey::make($dependency['module'], $dependency['action']);
                }
            }

            $map[$action->key($this->slug)] = array_values(array_unique($required));
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->getLabel(),
            'icon' => $this->icon,
            'group' => $this->getGroup(),
            'description' => $this->getDescription(),
            'sort' => $this->sort,
            'conditionable' => $this->conditionable,
            'guards' => $this->guards,
            'actions' => array_values(array_map(
                fn (ModuleAction $action): array => $action->toArray($this->slug),
                $this->actions,
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCacheArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'label_explicit' => $this->labelExplicit,
            'icon' => $this->icon,
            'group' => $this->group,
            'description' => $this->description,
            'sort' => $this->sort,
            'hidden' => is_bool($this->hidden) ? $this->hidden : $this->isHidden(),
            'conditionable' => $this->conditionable,
            'guards' => $this->guards,
            'dependencies' => $this->dependencies,
            'definition' => $this->definitionClass,
            'actions' => array_map(static fn (ModuleAction $action): array => [
                'name' => $action->name,
                'label' => $action->label,
                'danger' => $action->danger,
                'description' => $action->description,
                'implies' => $action->implies,
                'conditions' => $action->conditions,
            ], $this->actions),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromCacheArray(array $data): self
    {
        $module = new self((string) $data['slug']);
        $module->label = (string) ($data['label'] ?? $data['slug']);
        $module->labelExplicit = (bool) ($data['label_explicit'] ?? true);
        $module->icon = isset($data['icon']) ? (string) $data['icon'] : null;
        $module->group = (string) ($data['group'] ?? 'General');
        $module->description = isset($data['description']) ? (string) $data['description'] : null;
        $module->sort = (int) ($data['sort'] ?? 0);
        $module->hidden = (bool) ($data['hidden'] ?? false);
        $module->conditionable = array_values($data['conditionable'] ?? []);
        $module->guards = array_values($data['guards'] ?? []);
        $module->dependencies = array_values($data['dependencies'] ?? []);
        $module->definitionClass = isset($data['definition']) ? (string) $data['definition'] : null;

        foreach ($data['actions'] ?? [] as $action) {
            if (! is_array($action)) {
                continue;
            }

            $name = (string) $action['name'];
            $module->actions[$name] = new ModuleAction(
                name: $name,
                label: (string) ($action['label'] ?? $name),
                danger: (bool) ($action['danger'] ?? false),
                description: isset($action['description']) ? (string) $action['description'] : null,
                implies: array_values($action['implies'] ?? []),
                conditions: array_values($action['conditions'] ?? []),
            );
        }

        return $module;
    }
}
