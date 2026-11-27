<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Schemas\Token\PersonalAccessTokenSchema;
use Modules\Catalog\Schemas\Category\CategoryProductSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Support\Tenancy\TenantTableInventory;
use Modules\Media\Schemas\Folder\FolderSchema;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;

uses(RefreshDatabase::class);

it('lists the expected store-owned tables and excludes tenant metadata, pivots and key-only tables', function () {
    // Independent of TenantTableInventory's own constants: these are the
    // Phase 2 tables the migration must protect.
    $storeOwned = [
        ProductSchema::TABLE,
        VariantSchema::TABLE,
        OrderSchema::TABLE,
        FolderSchema::TABLE,
        MenuSchema::TABLE,
    ];

    $excluded = [
        TenantSchema::TABLE,
        TenantDomainSchema::TABLE,
        PersonalAccessTokenSchema::TABLE,
        CategoryProductSchema::TABLE,
        VariantOptionValueSchema::TABLE,
    ];

    foreach ($storeOwned as $table) {
        expect(TenantTableInventory::isStoreOwned($table))->toBeTrue()
            ->and(TenantTableInventory::storeOwned())->toContain($table);
    }

    foreach ($excluded as $table) {
        expect(TenantTableInventory::isStoreOwned($table))->toBeFalse()
            ->and(TenantTableInventory::storeOwned())->not->toContain($table);
    }

    expect(TenantTableInventory::pivots())->toContain(CategoryProductSchema::TABLE, VariantOptionValueSchema::TABLE);
});

it('creates immutability triggers only on store-owned tables', function () {
    $triggerNames = function (string $table): array {
        $trigger = $table.'_tenant_immutable';
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select('SELECT name FROM sqlite_master WHERE type = ? AND name = ?', ['trigger', $trigger]);
        } elseif ($driver === 'pgsql') {
            $rows = DB::select(
                'SELECT t.tgname FROM pg_trigger AS t JOIN pg_class AS c ON c.oid = t.tgrelid '
                .'WHERE t.tgname = ? AND c.relname = ? AND NOT t.tgisinternal',
                [$trigger, $table],
            );
        } else {
            $this->markTestSkipped("Trigger introspection is not implemented for [{$driver}].");

            return [];
        }

        return array_map(
            static fn (object $row): string => (string) ($row->name ?? $row->tgname),
            $rows,
        );
    };

    $storeOwned = [ProductSchema::TABLE, VariantSchema::TABLE, OrderSchema::TABLE, FolderSchema::TABLE, MenuSchema::TABLE];
    $excluded = [
        TenantSchema::TABLE,
        TenantDomainSchema::TABLE,
        PersonalAccessTokenSchema::TABLE,
        CategoryProductSchema::TABLE,
        VariantOptionValueSchema::TABLE,
    ];

    foreach ($storeOwned as $table) {
        expect(Schema::hasTable($table))->toBeTrue()
            ->and($triggerNames($table))->toContain($table.'_tenant_immutable');
    }

    foreach ($excluded as $table) {
        expect(Schema::hasTable($table))->toBeTrue()
            ->and($triggerNames($table))->not->toContain($table.'_tenant_immutable');
    }
});
