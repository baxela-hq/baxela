<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Tests\Feature\HelperTrait;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionSchema;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function productWithPricedVariant(string $slug, int $price, int $comparePrice = 0): Product
{
    $product = Product::factory()->create([
        'status' => ProductStatusEnum::IN_STOCK,
        'is_published' => true,
    ]);

    ProductTranslation::query()->create([
        'product_id' => $product->id,
        'language_id' => defaultLanguage()->id,
        'title' => 'Product '.$slug,
        'slug' => $slug,
        'content' => 'Content for '.$slug,
    ]);

    $product->variants()->create([
        VariantSchema::SKU => strtoupper($slug).'-1',
        VariantSchema::PRICE => $price,
        VariantSchema::COMPARE_PRICE => $comparePrice > 0 ? $comparePrice : null,
        VariantSchema::IS_DEFAULT => true,
    ]);

    return $product->refresh();
}

it('serves promoted prices and promotion info on the public list', function () {
    $promoted = productWithPricedVariant('promo-product', 100);
    $fullPrice = productWithPricedVariant('full-price-product', 50);

    // Scoped to ONE product — the other product keeps its plain price
    $promotion = Promotion::factory()->create([
        PromotionSchema::SCOPE => 'specific',
        PromotionSchema::TYPE => 'percent',
        PromotionSchema::VALUE => 20,
    ]);
    $promotion->syncScope([$promoted->id], []);

    $response = $this->getJson($this->baseUrl('/public/products'))
        ->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');

    expect($rows->get($promoted->id))
        ->price->toBe('80.00')
        ->compare_price->toBe('100.00')
        ->promotion->id->toBe((int) $promotion->id)
        ->promotion->type->toBe('percent')
        ->and($rows->get($fullPrice->id))
        ->price->toBe(50)
        ->promotion->toBeNull();
});

it('serves promoted prices on the public show, per variant', function () {
    $product = productWithPricedVariant('variant-product', 100);

    $second = $product->variants()->create([
        VariantSchema::SKU => 'VARIANT-PRODUCT-2',
        VariantSchema::PRICE => 200,
        VariantSchema::IS_DEFAULT => false,
    ]);

    Promotion::factory()->create([
        PromotionSchema::SCOPE => 'all',
        PromotionSchema::TYPE => 'fixed',
        PromotionSchema::VALUE => 25,
    ]);

    $this->getJson($this->baseUrl('/public/products/'.$product->id))
        ->assertOk()
        ->assertJsonPath('data.price', '75.00')
        ->assertJsonPath('data.compare_price', '100.00')
        ->assertJsonPath('data.variants.0.price', '75.00')
        ->assertJsonPath('data.variants.1.price', '175.00')
        ->assertJsonPath('data.variants.1.promotion.type', 'fixed');
});
