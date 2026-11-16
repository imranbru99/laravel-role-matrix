<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V4\Pages;

use Filament\Resources\Pages\CreateRecord;
use ImranDevBD\RoleMatrix\Filament\V4\RoleResource;

final class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;
}
