<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Registry;

final readonly class ModuleGroup
{
    /**
     * @param  list<Module>  $modules
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $modules,
        public int $sort,
    ) {}
}
