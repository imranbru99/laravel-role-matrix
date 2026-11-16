<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ImranDevBD\RoleMatrix\Traits\HasRoleMatrix;

class User extends Authenticatable
{
    use HasRoleMatrix;

    protected $guarded = [];
}
