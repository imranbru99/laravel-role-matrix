<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Enums;

enum GuardMode: string
{
    case Default = 'default';
    case Explicit = 'explicit';
    case Sanctum = 'sanctum';
}
