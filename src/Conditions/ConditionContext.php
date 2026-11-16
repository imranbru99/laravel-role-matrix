<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Database\Eloquent\Model;

final readonly class ConditionContext
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Model $user,
        public mixed $record,
        public string $permission,
        public array $context = [],
    ) {}
}
