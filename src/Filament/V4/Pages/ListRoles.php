<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V4\Pages;

use Filament\Resources\Pages\ListRecords;
use ImranDevBD\RoleMatrix\Filament\V4\RoleResource;

final class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;
}
