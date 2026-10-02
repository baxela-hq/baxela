<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Tests\Feature\HelperTrait;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function featuredCategory(string $slug): Category
{
    $category = Category::query()->create([CategorySchema::POSITION => 1]);

    $category->translations()->create([
        'language_id' => defaultLanguage()->id,
        'title' => 'Category '.$slug,
        'slug' => $slug,
    ]);

    return $category;
}

function featuredProduct(string $slug): Product
{
    $product = Product::factory()->create([
        'status' => ProductStatusEnum::IN_STOCK,
        'is_published' => true,
    ]);

    $product->translations()->create([
        'language_id' => defaultLanguage()->id,
        'title' => 'Product '.$slug,
        'slug' => $slug,
        'content' => 'Long-form content',
    ]);

    return $product;
}

it('lists the featured sections ordered by position', function () {
    $second = featuredProduct('second-product');
    $first = featuredProduct('first-product');
    featuredCategory('solo-category');

    $first->images()->create([
        'media_id' => 424242,
        'url' => 'https://cdn.test/first.jpg',
        'position' => 2,
        'collection' => 'photos',
    ]);
    $first->images()->create([
        'media_id' => 424242,
        'url' => 'https://cdn.test/cover.jpg',
        'position' => 1,
        'collection' => 'photos',
    ]);

    FeaturedItem::query()->create([
        FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_PRODUCT,
        FeaturedItemSchema::FEATUREDABLE_ID => $second->id,
        FeaturedItemSchema::POSITION => 2,
    ]);
    FeaturedItem::query()->create([
        FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_PRODUCT,
        FeaturedItemSchema::FEATUREDABLE_ID => $first->id,
        FeaturedItemSchema::POSITION => 1,
    ]);

    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/featured'))
        ->assertOk();

    expect(collect($response->json('data.product'))->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($response->json('data.product.0.translations'))->toBeArray()
        ->and($response->json('data.product.0.images'))->toHaveCount(2)
        ->and(collect($response->json('data.product.1.images'))->count())->toBe(0)
        ->and(collect($response->json('data.category'))->pluck('id')->all())->toBeArray();
});

it('syncs the featured selections, array order encoding position', function () {
    $a = featuredProduct('a');
    $b = featuredProduct('b');
    $category = featuredCategory('featured-cat');
    $this->actingAs($this->superAdminUser());

    $this->putJson($this->baseUrl('/admin/featured'), [
        'product_ids' => [$b->id, $a->id],
        'category_ids' => [$category->id],
    ])->assertOk();

    $positions = FeaturedItem::query()
        ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_PRODUCT)
        ->pluck(FeaturedItemSchema::POSITION, FeaturedItemSchema::FEATUREDABLE_ID);
    expect($positions[$b->id])->toBe(1)
        ->and($positions[$a->id])->toBe(2)
        ->and(FeaturedItem::query()->count())->toBe(3);

    // A second sync fully replaces the previous selection.
    $this->putJson($this->baseUrl('/admin/featured'), [
        'product_ids' => [$a->id],
        'category_ids' => [],
    ])->assertOk();

    expect(FeaturedItem::query()->count())->toBe(1)
        ->and(FeaturedItem::query()->value(FeaturedItemSchema::FEATUREDABLE_ID))->toBe($a->id);

    // The response mirrors the persisted selection.
    $response = $this->getJson($this->baseUrl('/admin/featured'))->assertOk();
    expect(collect($response->json('data.product'))->pluck('id')->all())->toBe([$a->id])
        ->and($response->json('data.category'))->toBe([]);
});

it('rejects unknown, duplicate or trashed ids', function () {
    $product = featuredProduct('kept');
    $trashed = featuredProduct('trashed-product');
    $trashed->delete();
    $this->actingAs($this->superAdminUser());

    $this->putJson($this->baseUrl('/admin/featured'), [
        'product_ids' => [$product->id, 999999],
        'category_ids' => [],
    ])->assertStatus(422);

    $this->putJson($this->baseUrl('/admin/featured'), [
        'product_ids' => [$product->id, $product->id],
        'category_ids' => [],
    ])->assertStatus(422);

    $this->putJson($this->baseUrl('/admin/featured'), [
        'product_ids' => [$trashed->id],
        'category_ids' => [],
    ])->assertStatus(422);

    // Both keys are required: a partial payload must not wipe a section.
    $this->putJson($this->baseUrl('/admin/featured'), [
        'product_ids' => [$product->id],
    ])->assertStatus(422);

    expect(FeaturedItem::query()->count())->toBe(0);
});

it('removes the featured row when a category is deleted', function () {
    $category = featuredCategory('doomed');
    FeaturedItem::query()->create([
        FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_CATEGORY,
        FeaturedItemSchema::FEATUREDABLE_ID => $category->id,
        FeaturedItemSchema::POSITION => 1,
    ]);
    $this->actingAs($this->superAdminUser());

    $this->deleteJson($this->baseUrl('/admin/categories/'.$category->id))
        ->assertNoContent();

    expect(FeaturedItem::query()->count())->toBe(0);
});

it('denies the featured endpoints without permission', function () {
    defaultLanguage();
    $this->actingAs(User::factory()->create());

    $this->getJson($this->baseUrl('/admin/featured'))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');

    $this->putJson($this->baseUrl('/admin/featured'), [
        'product_ids' => [],
        'category_ids' => [],
    ])->assertStatus(403)->assertJsonPath('code', 'http.403');
});
