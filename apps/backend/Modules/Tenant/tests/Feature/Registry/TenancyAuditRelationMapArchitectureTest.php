<?php

use Modules\Core\Support\Tenancy\TenantTableInventory;

/*
 * §4.3 layer 4: direct relations come from database FKs, while the nine
 * tenant-less pivots must be declared. Both halves of the map have to be
 * complete or `tenancy:audit` silently misses a cross-store reference.
 */

it('declares every tenant-less pivot exactly once', function () {
    $declared = array_column(config('core.tenancy.audit_pivots', []), 'pivot');

    expect($declared)->toBe(TenantTableInventory::pivots());
});

it('maps every pivot side to a store-owned table or the installation-level country table', function () {
    foreach (config('core.tenancy.audit_pivots', []) as $relation) {
        expect(TenantTableInventory::isStoreOwned($relation['left']['table']))->toBeTrue();

        if ($relation['right'] !== null) {
            expect(TenantTableInventory::isStoreOwned($relation['right']['table']))->toBeTrue();
        } else {
            expect($relation['pivot'])->toBe('shipping_zone_countries');
        }
    }
});
