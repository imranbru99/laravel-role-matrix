<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Enums;

enum RoleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
