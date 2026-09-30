<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;
use Modules\Inventory\Tests\Feature\HelperTrait;
use function Modules\Inventory\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * Product with one variant, one English translation, and a stock row.
 * Kept local: catalog factories do not create translations.
 */
function stockedProduct(int $quantity, string $sku = 'SKU-001', string $title = 'Coffee Mug'): array
{
    $product = Product::factory()->create();

    ProductTranslation::query()->create([
        'product_id' => $product->id,
        'language_id' => defaultLanguage()->id,
        'title' => $title,
        'slug' => Illuminate\Support\Str::slug($title).'-'.$product->id,
        'content' => 'Content',
        'description' => null,
    ]);

    $variant = Variant::factory()->ofProduct($product)->create([
        'sku' => $sku,
    ]);

    $stock = InventoryStock::factory()->ofVariant($variant, $quantity)->create();

    return [$product, $variant, $stock];
}

it('lists stocks with the variant and product title', function () {
    $this->actingAs($this->superAdminUser());

    [$product, $variant, $stock] = stockedProduct(12);

    $this->getJson($this->baseUrl('/admin/inventory-stocks'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $stock->id)
        ->assertJsonPath('data.0.variant_id', $variant->id)
        ->assertJsonPath('data.0.quantity', 12)
        ->assertJsonPath('data.0.variant.sku', 'SKU-001')
        ->assertJsonPath('data.0.variant.product.id', $product->id)
        ->assertJsonPath('data.0.variant.product.translations.0.title', 'Coffee Mug');
});

it('creates a stock for an existing variant', function () {
    $this->actingAs($this->superAdminUser());
    defaultLanguage();

    $variant = Variant::factory()->ofProduct(Product::factory()->create())->create();

    $this->postJson($this->baseUrl('/admin/inventory-stocks'), [
        InventoryStockSchema::VARIANT_ID => $variant->id,
        InventoryStockSchema::QUANTITY => 25,
    ])
        ->assertCreated()
        ->assertJsonPath('data.variant_id', $variant->id)
        ->assertJsonPath('data.quantity', 25);

    expect(
        InventoryStock::query()
            ->where(InventoryStockSchema::VARIANT_ID, $variant->id)
            ->value(InventoryStockSchema::QUANTITY)
    )->toBe(25);
});

it('rejects a stock for an unknown variant', function () {
    $this->actingAs($this->superAdminUser());

    $this->postJson($this->baseUrl('/admin/inventory-stocks'), [
        InventoryStockSchema::VARIANT_ID => 999999,
        InventoryStockSchema::QUANTITY => 5,
    ])->assertStatus(422);
});

it('updates the quantity of a stock', function () {
    $this->actingAs($this->superAdminUser());

    [, , $stock] = stockedProduct(3);

    $this->patchJson($this->baseUrl('/admin/inventory-stocks/'.$stock->id), [
        InventoryStockSchema::VARIANT_ID => $stock->variant_id,
        InventoryStockSchema::QUANTITY => 40,
    ])
        ->assertOk()
        ->assertJsonPath('data.quantity', 40);
});

it('deletes a stock', function () {
    $this->actingAs($this->superAdminUser());

    [, , $stock] = stockedProduct(7);

    $this->deleteJson($this->baseUrl('/admin/inventory-stocks/'.$stock->id))
        ->assertNoContent();

    expect(InventoryStock::query()->find($stock->id))->toBeNull();
});

it('filters stocks by variant, sku and low stock', function () {
    $this->actingAs($this->superAdminUser());

    [, $lowVariant, $lowStock] = stockedProduct(2, 'SKU-LOW', 'Low Mug');
    [, , $highStock] = stockedProduct(50, 'SKU-HIGH', 'High Mug');

    // exact variant id
    $this->getJson($this->baseUrl('/admin/inventory-stocks?filter[variant_id]='.$lowVariant->id))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $lowStock->id);

    // sku contains
    $this->getJson($this->baseUrl('/admin/inventory-stocks?filter[sku]=SKU'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // low stock window (threshold 5)
    $this->getJson($this->baseUrl('/admin/inventory-stocks?filter[low_stock]=true'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $lowStock->id)
        ->assertJsonPath('data.0.quantity', 2);

    expect($highStock->quantity)->toBeGreaterThan(5);
});

it('sorts stocks by quantity', function () {
    $this->actingAs($this->superAdminUser());

    stockedProduct(30, 'SKU-HIGH', 'High Mug');
    stockedProduct(4, 'SKU-LOW', 'Low Mug');

    $response = $this->getJson($this->baseUrl('/admin/inventory-stocks?sort=quantity'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $quantities = collect($response->json('data'))->pluck('quantity');

    expect($quantities->first())->toBeLessThan($quantities->last());
});
