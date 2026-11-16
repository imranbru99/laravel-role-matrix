<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ImranDevBD\RoleMatrix\Traits\HasConditionalAccess;
use ImranDevBD\RoleMatrix\Traits\HasRoleMatrix;

/**
 * Reference subject so the public traits are part of the analysed package.
 */
class MatrixSubject extends Authenticatable
{
    use HasConditionalAccess;
    use HasRoleMatrix;

    protected $table = 'users';

    protected $guarded = [];
}
