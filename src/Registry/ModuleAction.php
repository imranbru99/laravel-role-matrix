<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Registry;

final class ModuleAction
{
    /**
     * @param  list<string>  $implies
     * @param  list<string>  $conditions
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly bool $danger = false,
        public readonly ?string $description = null,
        public readonly array $implies = [],
        public readonly array $conditions = [],
    ) {}

    public function key(string $module): string
    {
        return $module.'.'.$this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $module): array
    {
        return [
            'name' => $this->name,
            'key' => $this->key($module),
            'label' => $this->label,
            'danger' => $this->danger,
            'description' => $this->description,
            'implies' => $this->implies,
            'conditions' => $this->conditions,
        ];
    }
}
