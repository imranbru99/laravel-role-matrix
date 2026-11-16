<?php

declare(strict_types=1);

use ImranDevBD\RoleMatrix\Support\PermissionKey;
use ImranDevBD\RoleMatrix\Support\WildcardMatcher;

it('matches exact permissions and ignores case', function () {
    expect(WildcardMatcher::matches('inventory.view', 'INVENTORY.VIEW'))->toBeTrue()
        ->and(WildcardMatcher::matches('inventory.view', 'inventory.update'))->toBeFalse();
});

it('matches star, star-dot-star, module wildcards, and action wildcards', function () {
    expect(WildcardMatcher::matches('*', 'inventory.view'))->toBeTrue()
        ->and(WildcardMatcher::matches('*', 'a.b.c'))->toBeTrue()
        ->and(WildcardMatcher::matches('*.*', 'inventory.view'))->toBeTrue()
        ->and(WildcardMatcher::matches('*.*', 'a.b.c'))->toBeFalse()
        ->and(WildcardMatcher::matches('inventory.*', 'inventory.view'))->toBeTrue()
        ->and(WildcardMatcher::matches('inventory.*', 'inventory.view.extra'))->toBeFalse()
        ->and(WildcardMatcher::matches('*.view', 'sales.view'))->toBeTrue()
        ->and(WildcardMatcher::matches('*.view', 'sales.update'))->toBeFalse()
        ->and(WildcardMatcher::matches('inventory.*', 'other.view'))->toBeFalse();
});

it('rejects empty patterns and permissions', function () {
    expect(WildcardMatcher::matches('', 'inventory.view'))->toBeFalse()
        ->and(WildcardMatcher::matches('*', ''))->toBeFalse()
        ->and(WildcardMatcher::matches('inventory.*', 'inventory.'))->toBeFalse();
});

it('builds concrete keys and covering patterns', function () {
    expect(PermissionKey::make('Inventory', 'View'))->toBe('inventory.view')
        ->and(PermissionKey::isConcrete('inventory.view'))->toBeTrue()
        ->and(PermissionKey::isPattern('inventory.*'))->toBeTrue()
        ->and(PermissionKey::covering('inventory.view'))->toBe([
            'inventory.view',
            '*',
            '*.*',
            'inventory.*',
            '*.view',
        ]);
});

it('rejects reserved or malformed module slugs', function () {
    PermissionKey::assertModule('*');
})->throws(ImranDevBD\RoleMatrix\Exceptions\InvalidModuleException::class);
