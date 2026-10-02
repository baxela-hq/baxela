<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
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

function featureCategory(Category $category, int $position): Category
{
    FeaturedItem::query()->create([
        FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_CATEGORY,
        FeaturedItemSchema::FEATUREDABLE_ID => $category->{CategorySchema::ID},
        FeaturedItemSchema::POSITION => $position,
    ]);

    return $category;
}

it('lists only featured categories ordered by featured position when filtered', function () {
    featureCategory(publicCategory('second', [
        CategorySchema::POSITION => 1,
        CategorySchema::IMAGE_URL => '/storage/categories/second.png',
    ]), position: 2);
    featureCategory(publicCategory('first', [
        CategorySchema::POSITION => 2,
    ]), position: 1);
    publicCategory('hidden');

    $response = $this->getJson($this->baseUrl('/public/categories').'?featured=true')
        ->assertOk();

    $categories = collect($response->json('data'));

    expect($categories->pluck('slug')->all())->toBe(['first', 'second'])
        ->and($categories->last()['image_url'])->toBe('/storage/categories/second.png');
});

it('lists every category regardless of featured items when unfiltered', function () {
    featureCategory(publicCategory('featured'), position: 1);
    publicCategory('regular');

    $response = $this->getJson($this->baseUrl('/public/categories'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('slug'))->toHaveCount(2);
});
