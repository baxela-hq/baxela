<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Tests\Feature\HelperTrait;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function publicCategory(string $slug, array $attributes = []): Category
{
    $category = Category::query()->create($attributes);

    $category->translations()->create([
        'language_id' => defaultLanguage()->id,
        'title' => 'Category '.$slug,
        'slug' => $slug,
    ]);

    return $category;
}

it('lists only featured categories ordered by position when filtered', function () {
    publicCategory('second', [
        CategorySchema::IS_FEATURED => true,
        CategorySchema::POSITION => 2,
        CategorySchema::IMAGE_URL => '/storage/categories/second.png',
    ]);
    publicCategory('first', [
        CategorySchema::IS_FEATURED => true,
        CategorySchema::POSITION => 1,
    ]);
    publicCategory('hidden', [CategorySchema::IS_FEATURED => false]);

    $response = $this->getJson($this->baseUrl('/public/categories').'?featured=true')
        ->assertOk();

    $categories = collect($response->json('data'));

    expect($categories->pluck('slug')->all())->toBe(['first', 'second'])
        ->and($categories->last()['image_url'])->toBe('/storage/categories/second.png');
});

it('lists every category regardless of the featured flag when unfiltered', function () {
    publicCategory('featured', [CategorySchema::IS_FEATURED => true]);
    publicCategory('regular', [CategorySchema::IS_FEATURED => false]);

    $response = $this->getJson($this->baseUrl('/public/categories'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('slug'))->toHaveCount(2);
});
