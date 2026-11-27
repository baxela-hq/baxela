<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

uses(RefreshDatabase::class);

it('rejects a direct tenant_id move but allows a non-tenant column update', function () {
    $product = Product::factory()->create();
    $id = (int) $product->getKey();
    $originalTenant = (int) $product->getAttribute(TenantColumn::TENANT_ID);

    // A savepoint keeps the outer RefreshDatabase transaction usable after
    // PostgreSQL aborts the failing statement.
    $rejected = false;

    try {
        DB::transaction(fn () => DB::table(ProductSchema::TABLE)
            ->where(ProductSchema::ID, $id)
            ->update([TenantColumn::TENANT_ID => $originalTenant + 1]));
    } catch (QueryException) {
        $rejected = true;
    }

    expect($rejected)->toBeTrue()
        ->and((int) DB::table(ProductSchema::TABLE)->where(ProductSchema::ID, $id)->value(TenantColumn::TENANT_ID))
        ->toBe($originalTenant);

    $affected = DB::table(ProductSchema::TABLE)
        ->where(ProductSchema::ID, $id)
        ->update([ProductSchema::IS_PUBLISHED => true]);

    expect($affected)->toBe(1)
        ->and((int) DB::table(ProductSchema::TABLE)->where(ProductSchema::ID, $id)->value(TenantColumn::TENANT_ID))
        ->toBe($originalTenant);
});
