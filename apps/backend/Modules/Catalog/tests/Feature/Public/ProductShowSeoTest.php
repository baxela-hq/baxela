<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Tests\Feature\HelperTrait;
use Modules\Core\Models\Language;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * A published product with one default variant and an en translation;
 * $stock null = untracked (no inventory row), an int = tracked quantity.
 */
function seoProduct(?int $stock): array
{
    $product = Product::factory()->create([
        ProductSchema::STATUS => ProductStatusEnum::IN_STOCK,
        ProductSchema::IS_PUBLISHED => true,
    ]);

    $product->translations()->create([
        PTSchema::LANGUAGE_ID => defaultLanguage()->id,
        PTSchema::TITLE => 'SEO Product',
        PTSchema::SLUG => 'seo-product',
        PTSchema::DESCRIPTION => 'A product for structured-data assertions',
        PTSchema::CONTENT => '<p>Content</p>',
    ]);

    $variant = Variant::factory()->ofProduct($product)->create([
        VariantSchema::SKU => 'SEO-1',
        VariantSchema::PRICE => 25.5,
        VariantSchema::IS_DEFAULT => true,
    ]);

    if ($stock !== null) {
        InventoryStock::factory()->create([
            InventoryStockSchema::VARIANT_ID => $variant->id,
            InventoryStockSchema::QUANTITY => $stock,
        ]);
    }

    return [$product, $variant];
}

function secondLocaleSlug(Product $product, string $slug): void
{
    $fa = Language::query()->firstOrCreate(
        ['code' => 'fa'],
        ['locale' => 'fa-IR', 'name' => 'Persian', 'code3' => 'fas', 'is_active' => true],
    );

    $product->translations()->create([
        PTSchema::LANGUAGE_ID => $fa->id,
        PTSchema::TITLE => 'محصول سئو',
        PTSchema::SLUG => $slug,
        PTSchema::CONTENT => '<p>محتوا</p>',
    ]);
}

it('reports purchasable availability and the per-locale slug map', function () {
    [$product] = seoProduct(5);
    secondLocaleSlug($product, 'محصول-سئو');

    $data = $this->getJson($this->baseUrl('/public/products/seo-product'))
        ->assertOk()
        ->json('data');

    // Tracked stock on the shelf → purchasable (gap-B2 D6).
    expect($data['availability'])->toBe('in_stock')
        // Every offered locale's slug, for the storefront's hreflang map.
        ->and($data['slugs'])->toBe(['en' => 'seo-product', 'fa' => 'محصول-سئو'])
        ->and($data['price'])->toBe(25.5);
});

it('reports out of stock when every tracked variant is empty', function () {
    [$product] = seoProduct(0);

    $data = $this->getJson($this->baseUrl('/public/products/seo-product'))
        ->assertOk()
        ->json('data');

    expect($data['availability'])->toBe('out_of_stock');
});

it('treats untracked variants as purchasable', function () {
    [$product] = seoProduct(null);

    $data = $this->getJson($this->baseUrl('/public/products/seo-product'))
        ->assertOk()
        ->json('data');

    expect($data['availability'])->toBe('in_stock');
});
