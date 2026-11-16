<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Contracts;

use ImranDevBD\RoleMatrix\Registry\Module;

interface ModuleContract
{
    public function configure(Module $module): void;
}
