<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use ImranDevBD\RoleMatrix\Traits\HasRoleMatrix;

class User extends Authenticatable
{
    use HasFactory;
    use HasRoleMatrix;

    protected $guarded = [];

    protected $hidden = ['password'];
}
