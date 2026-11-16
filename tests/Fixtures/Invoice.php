<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Traits\HasConditionalAccess;

final class Invoice extends Model
{
    use HasConditionalAccess;

    protected $table = 'invoices';

    protected $guarded = [];

    public $timestamps = false;
}
