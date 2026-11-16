<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Enums;

enum PermissionEffect: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}
