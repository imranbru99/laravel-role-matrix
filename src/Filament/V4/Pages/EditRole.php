<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\V4\Pages;

use Filament\Resources\Pages\EditRecord;
use ImranDevBD\RoleMatrix\Filament\V4\RoleResource;

final class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;
}
