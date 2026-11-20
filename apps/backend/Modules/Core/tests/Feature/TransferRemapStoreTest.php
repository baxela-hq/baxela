<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Support\Transfer\TransferRemapStore;

uses(RefreshDatabase::class);

it('records and resolves a mapping within its source namespace', function () {
    $store = new TransferRemapStore;
    $source = (string) Str::uuid();

    $store->record($source, 'tax', 'tax-classes', 42, 108);

    expect($store->resolve($source, 'tax', 'tax-classes', 42))->toBe(108)
        ->and($store->resolve($source, 'tax', 'tax-classes', 43))->toBeNull()
        ->and($store->resolve($source, 'tax', 'tax-rates', 42))->toBeNull();
});

it('overwrites the mapping on re-record so repeated restores converge', function () {
    $store = new TransferRemapStore;
    $source = (string) Str::uuid();

    $store->record($source, 'order', 'orders', 7, 500);
    $store->record($source, 'order', 'orders', 7, 777);

    expect($store->resolve($source, 'order', 'orders', 7))->toBe(777)
        // Upsert, not pile-up.
        ->and(TransferRemap::query()->count())->toBe(1);
});

it('namespaces by source installation so overlapping ids never collide (F1)', function () {
    $store = new TransferRemapStore;
    $storeA = (string) Str::uuid();
    $storeB = (string) Str::uuid();

    // Both stores carry user 42; they map to DIFFERENT local ids.
    $store->record($storeA, 'user', 'users', 42, 108);
    $store->record($storeB, 'user', 'users', 42, 209);

    expect($store->resolve($storeA, 'user', 'users', 42))->toBe(108)
        ->and($store->resolve($storeB, 'user', 'users', 42))->toBe(209);
});

it('maps legacy files into the reserved unidentified namespace', function () {
    $store = new TransferRemapStore;

    $store->record(TransferRemapInterface::UNIDENTIFIED, 'catalog', 'products', 5, 17);

    // A different identified source must NOT see the unidentified mapping.
    expect($store->resolve(TransferRemapInterface::UNIDENTIFIED, 'catalog', 'products', 5))->toBe(17)
        ->and($store->resolve((string) Str::uuid(), 'catalog', 'products', 5))->toBeNull();
});

it('returns the mapping even when the local row is gone — dangling is the consumer call', function () {
    $store = new TransferRemapStore;
    $source = (string) Str::uuid();

    $store->record($source, 'catalog', 'variants', 9, 31);
    // The local row is deleted afterwards; the ledger records what WAS
    // mapped — consumers verify existence through the owning module's
    // gateway and fall through with a dangling warning (C2).
    expect($store->resolve($source, 'catalog', 'variants', 9))->toBe(31);
});

it('keeps entity id spaces separate inside one module', function () {
    $store = new TransferRemapStore;
    $source = (string) Str::uuid();

    // Catalog carries products AND variants — distinct id spaces (C1).
    $store->record($source, 'catalog', 'products', 3, 100);
    $store->record($source, 'catalog', 'variants', 3, 300);

    expect($store->resolve($source, 'catalog', 'products', 3))->toBe(100)
        ->and($store->resolve($source, 'catalog', 'variants', 3))->toBe(300)
        ->and(TransferRemap::query()->count())->toBe(2);
});
