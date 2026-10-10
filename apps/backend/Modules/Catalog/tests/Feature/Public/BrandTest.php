<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Tests\Feature\HelperTrait;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function publicBrand(string $slug, array $attributes = []): Brand
{
    $brand = Brand::query()->create($attributes);

    $brand->translations()->create([
        'language_id' => defaultLanguage()->id,
        'title' => 'Brand '.$slug,
        'slug' => $slug,
    ]);

    return $brand;
}

function featureBrand(Brand $brand, int $position): Brand
{
    FeaturedItem::query()->create([
        FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_BRAND,
        FeaturedItemSchema::FEATUREDABLE_ID => $brand->{BrandSchema::ID},
        FeaturedItemSchema::POSITION => $position,
    ]);

    return $brand;
}

it('lists only featured brands ordered by featured position when filtered', function () {
    featureBrand(publicBrand('second', [
        BrandSchema::POSITION => 1,
        BrandSchema::IMAGE_URL => '/storage/brands/second.png',
    ]), position: 2);
    featureBrand(publicBrand('first', [
        BrandSchema::POSITION => 2,
    ]), position: 1);
    publicBrand('hidden');

    $response = $this->getJson($this->baseUrl('/public/brands').'?featured=true')
        ->assertOk();

    $brands = collect($response->json('data'));

    expect($brands->pluck('slug')->all())->toBe(['first', 'second'])
        ->and($brands->last()['image_url'])->toBe('/storage/brands/second.png');
});

it('lists every brand regardless of featured items when unfiltered', function () {
    featureBrand(publicBrand('featured'), position: 1);
    publicBrand('regular');

    $response = $this->getJson($this->baseUrl('/public/brands'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('slug'))->toHaveCount(2);
});

it('shows a brand by slug', function () {
    $brand = publicBrand('urban-edge');

    $this->getJson($this->baseUrl('/public/brands/urban-edge'))
        ->assertOk()
        ->assertJsonPath('data.id', $brand->{BrandSchema::ID})
        ->assertJsonPath('data.slug', 'urban-edge')
        ->assertJsonPath('data.title', 'Brand urban-edge');
});

it('shows a brand by id', function () {
    $brand = publicBrand('north-peak');

    $this->getJson($this->baseUrl('/public/brands/'.$brand->{BrandSchema::ID}))
        ->assertOk()
        ->assertJsonPath('data.slug', 'north-peak');
});

it('returns 404 for an unknown brand', function () {
    $this->getJson($this->baseUrl('/public/brands/unknown-brand'))
        ->assertStatus(404)
        ->assertJsonPath('code', 'http.404');
});
