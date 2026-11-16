<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Enums;

enum ImportMode: string
{
    case Merge = 'merge';
    case Replace = 'replace';
    case DryRun = 'dry-run';
}
