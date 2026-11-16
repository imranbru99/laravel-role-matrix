<?php

declare(strict_types=1);

use ImranDevBD\RoleMatrix\Tests\TestCase;

uses(TestCase::class)->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
