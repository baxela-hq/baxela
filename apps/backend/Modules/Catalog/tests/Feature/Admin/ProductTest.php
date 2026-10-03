<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Image;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Tests\Feature\HelperTrait;
use Modules\Inventory\Models\InventoryStock;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

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

it('attaches one photo per variant on create', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->postJson($this->baseUrl('/admin/products'), productPayload([
        'images' => [[
            'media_id' => 55,
            'url' => 'https://example.com/gallery.jpg',
            'collection' => 'photos',
            'position' => 1,
        ]],
        'variants' => [[
            'sku' => 'SKU-'.uniqid(),
            'price' => '150',
            'quantity' => 5,
            'is_default' => true,
            'image' => ['media_id' => 77, 'url' => 'https://example.com/variant.jpg'],
        ]],
    ]))->assertCreated();

    $product = Product::query()->find($response->json('data.id'));
    $variant = $product->variants()->first();

    $image = Image::query()->where(ImageSchema::VARIANT_ID, $variant->id)->first();

    expect($image)->not->toBeNull()
        ->and($image->{ImageSchema::MEDIA_ID})->toBe(77)
        ->and($image->{ImageSchema::PRODUCT_ID})->toBe($product->id)
        // the variant photo stays out of the product gallery
        ->and($product->images()->pluck(ImageSchema::MEDIA_ID)->all())->toBe([55]);

    $this->getJson($this->baseUrl('/admin/products/'.$product->id))
        ->assertOk()
        ->assertJsonPath('data.variants.0.image.media_id', 77)
        ->assertJsonPath('data.variants.0.image.variant_id', $variant->id)
        ->assertJsonCount(1, 'data.images');
});

it('re-attaches variant photos to the recreated variants on update', function () {
    $this->actingAs($this->superAdminUser());

    $sku = 'SKU-'.uniqid();
    $variant = ['sku' => $sku, 'price' => '150', 'quantity' => 5, 'is_default' => true];

    $productId = $this->postJson($this->baseUrl('/admin/products'), productPayload([
        'variants' => [$variant + ['image' => ['media_id' => 77, 'url' => 'https://example.com/variant.jpg']]],
    ]))->assertCreated()->json('data.id');

    $oldVariantId = Product::query()->find($productId)->variants()->first()->id;

    // Variants are deleted and recreated on every save — the photo must
    // follow the fresh variant id.
    $this->patchJson($this->baseUrl('/admin/products/'.$productId), productPayload([
        'variants' => [$variant + ['image' => ['media_id' => 77, 'url' => 'https://example.com/variant.jpg']]],
    ]))->assertOk();

    $newVariant = Product::query()->find($productId)->variants()->first();
    expect($newVariant->id)->not->toBe($oldVariantId)
        ->and($newVariant->images()->pluck(ImageSchema::MEDIA_ID)->all())->toBe([77]);

    // image: null leaves the recreated variant without a photo
    $this->patchJson($this->baseUrl('/admin/products/'.$productId), productPayload([
        'variants' => [$variant + ['image' => null]],
    ]))->assertOk();

    expect(Image::query()
        ->where(ImageSchema::PRODUCT_ID, $productId)
        ->whereNotNull(ImageSchema::VARIANT_ID)
        ->count())->toBe(0);
});

it('creates two products and lists them newest-first', function () {
    $this->actingAs($this->superAdminUser());

    $firstId = $this->postJson($this->baseUrl('/admin/products'), productPayload())
        ->assertCreated()
        ->json('data.id');

    $secondId = $this->postJson($this->baseUrl('/admin/products'), productPayload([
        'translations' => [[
            'language' => 'en',
            'title' => 'Plain Product',
            'slug' => 'plain-product',
            'content' => 'Long-form content',
            'description' => null,
        ]],
    ]))
        ->assertCreated()
        ->json('data.id');

    $ids = collect(
        $this->getJson($this->baseUrl('/admin/products'))->json('data')
    )->pluck('id');

    expect($ids)->toContain($firstId)->toContain($secondId)
        ->and($ids->first())->toBe($secondId);
});
