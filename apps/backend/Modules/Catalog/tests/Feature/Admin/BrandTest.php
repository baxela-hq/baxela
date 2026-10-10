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

function brandPayload(array $overrides = []): array
{
    defaultLanguage();

    return array_merge([
        'position' => null,
        'image_media_id' => null,
        'image_url' => null,
        'translations' => [[
            'language' => 'en',
            'title' => 'UrbanEdge',
            'slug' => 'urban-edge',
            'description' => null,
        ]],
    ], $overrides);
}

it('creates a brand with translations', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->postJson($this->baseUrl('/admin/brands'), brandPayload())
        ->assertCreated();

    $brand = Brand::query()->find($response->json('data.id'));

    expect($brand)->not->toBeNull()
        ->and($brand->translations()->count())->toBe(1)
        ->and($brand->translations()->first()->title)->toBe('UrbanEdge');
});

it('creates a brand with a logo', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->postJson($this->baseUrl('/admin/brands'), brandPayload([
        'image_media_id' => 3,
        'image_url' => '/storage/brands/urban-edge.png',
    ]))->assertCreated()
        ->assertJsonPath('data.image_url', '/storage/brands/urban-edge.png')
        ->assertJsonPath('data.image_media_id', 3);

    $brand = Brand::query()->find($response->json('data.id'));

    expect($brand->{BrandSchema::IMAGE_URL})->toBe('/storage/brands/urban-edge.png')
        ->and($brand->{BrandSchema::IMAGE_MEDIA_ID})->toBe(3);
});

it('updates the logo', function () {
    $this->actingAs($this->superAdminUser());

    $id = $this->postJson($this->baseUrl('/admin/brands'), brandPayload())
        ->assertCreated()->json('data.id');

    $this->patchJson($this->baseUrl("/admin/brands/{$id}"), brandPayload([
        'image_url' => '/storage/brands/urban-edge-2.png',
    ]))->assertOk();

    $brand = Brand::query()->find($id);

    expect($brand->{BrandSchema::IMAGE_URL})->toBe('/storage/brands/urban-edge-2.png');
});

it('rejects a slug already taken in the same language', function () {
    $this->actingAs($this->superAdminUser());

    $this->postJson($this->baseUrl('/admin/brands'), brandPayload())->assertCreated();

    $this->postJson($this->baseUrl('/admin/brands'), brandPayload([
        'translations' => [[
            'language' => 'en',
            'title' => 'Other Brand',
            'slug' => 'urban-edge',
            'description' => null,
        ]],
    ]))->assertStatus(422)
        ->assertJsonPath('code', 'http.422');

    expect(Brand::count())->toBe(1);
});

it('removes the featured row when a brand is deleted', function () {
    $this->actingAs($this->superAdminUser());

    $id = $this->postJson($this->baseUrl('/admin/brands'), brandPayload())
        ->assertCreated()->json('data.id');

    FeaturedItem::query()->create([
        FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_BRAND,
        FeaturedItemSchema::FEATUREDABLE_ID => (int) $id,
        FeaturedItemSchema::POSITION => 1,
    ]);

    $this->deleteJson($this->baseUrl('/admin/brands/'.$id))
        ->assertNoContent();

    expect(Brand::count())->toBe(0)
        ->and(FeaturedItem::query()->count())->toBe(0);
});
