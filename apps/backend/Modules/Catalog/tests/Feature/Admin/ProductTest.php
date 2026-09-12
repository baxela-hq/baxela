<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Tests\Feature\HelperTrait;
use function Modules\Catalog\Tests\Feature\defaultLanguage;
use Modules\Inventory\Models\InventoryStock;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function productPayload(array $overrides = []): array
{
    defaultLanguage();

    return array_merge([
        'type' => 'simple',
        'status' => 'in_stock',
        'is_published' => true,
        'categories' => [Category::factory()->create()->id],
        'translations' => [[
            'language' => 'en',
            'title' => 'Test Product',
            'slug' => 'test-product',
            'content' => 'Long-form content',
            'description' => 'Short description',
        ]],
        'variants' => [[
            'sku' => 'SKU-'.uniqid(),
            'price' => '150',
            'quantity' => 5,
            'is_default' => true,
        ]],
    ], $overrides);
}

it('creates a product with translations, variant and stock', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->postJson($this->baseUrl('/admin/products'), productPayload())
        ->assertCreated();

    $product = Product::query()->find($response->json('data.id'));

    expect($product)->not->toBeNull()
        ->and($product->{ProductSchema::IS_PUBLISHED})->toBeTrue()
        ->and($product->translations()->count())->toBe(1)
        ->and($product->translations()->first()->{ProductTranslationSchema::SLUG})->toBe('test-product')
        ->and($product->variants()->count())->toBe(1);

    // The variant's quantity landed in inventory
    $variant = $product->variants()->first();
    expect((int) InventoryStock::query()->where('variant_id', $variant->id)->value('quantity'))->toBe(5);
});

it('rejects duplicate language pairs in translations', function () {
    $this->actingAs($this->superAdminUser());

    $payload = productPayload([
        'translations' => [
            ['language' => 'en', 'title' => 'One', 'slug' => 'one', 'content' => 'Content', 'description' => null],
            ['language' => 'en', 'title' => 'Two', 'slug' => 'one', 'content' => 'Content', 'description' => null],
        ],
    ]);

    $this->postJson($this->baseUrl('/admin/products'), $payload)
        ->assertStatus(422)
        ->assertJsonPath('code', 'http.422');

    expect(Product::count())->toBe(0);
});

it('updates a product and its translations', function () {
    $this->actingAs($this->superAdminUser());

    $productId = $this->postJson($this->baseUrl('/admin/products'), productPayload())
        ->assertCreated()->json('data.id');

    $this->patchJson($this->baseUrl('/admin/products/'.$productId), productPayload([
        'is_published' => false,
        'translations' => [[
            'language' => 'en',
            'title' => 'Renamed Product',
            'slug' => 'renamed-product',
            'content' => 'Long-form content',
            'description' => null,
        ]],
    ]))->assertOk();

    $product = Product::query()->find($productId);

    expect($product->{ProductSchema::IS_PUBLISHED})->toBeFalse()
        ->and($product->translations()->first()->{ProductTranslationSchema::TITLE})->toBe('Renamed Product');
});

it('soft-deletes a product', function () {
    $this->actingAs($this->superAdminUser());

    $productId = $this->postJson($this->baseUrl('/admin/products'), productPayload())
        ->assertCreated()->json('data.id');

    $this->deleteJson($this->baseUrl('/admin/products/'.$productId))
        ->assertNoContent();

    expect(Product::query()->find($productId))->toBeNull()
        ->and(Product::withTrashed()->find($productId))->not->toBeNull();
});
