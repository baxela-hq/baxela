<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Tests\Feature\HelperTrait;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function categoryPayload(array $overrides = []): array
{
    defaultLanguage();

    return array_merge([
        'parent_id' => null,
        'position' => null,
        'image_media_id' => null,
        'image_url' => null,
        'is_featured' => false,
        'translations' => [[
            'language' => 'en',
            'title' => 'Shoes',
            'slug' => 'shoes',
            'description' => null,
        ]],
    ], $overrides);
}

it('creates a category with translations', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->postJson($this->baseUrl('/admin/categories'), categoryPayload())
        ->assertCreated();

    $category = Category::query()->find($response->json('data.id'));

    expect($category)->not->toBeNull()
        ->and($category->translations()->count())->toBe(1)
        ->and($category->translations()->first()->title)->toBe('Shoes');
});

it('creates a featured category with an image', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->postJson($this->baseUrl('/admin/categories'), categoryPayload([
        'image_media_id' => 3,
        'image_url' => '/storage/categories/shoes.png',
        'is_featured' => true,
    ]))->assertCreated()
        ->assertJsonPath('data.is_featured', true)
        ->assertJsonPath('data.image_url', '/storage/categories/shoes.png')
        ->assertJsonPath('data.image_media_id', 3);

    $category = Category::query()->find($response->json('data.id'));

    expect($category->{CategorySchema::IS_FEATURED})->toBeTrue()
        ->and($category->{CategorySchema::IMAGE_URL})->toBe('/storage/categories/shoes.png')
        ->and($category->{CategorySchema::IMAGE_MEDIA_ID})->toBe(3);
});

it('updates the featured flag and image', function () {
    $this->actingAs($this->superAdminUser());

    $id = $this->postJson($this->baseUrl('/admin/categories'), categoryPayload())
        ->assertCreated()->json('data.id');

    $this->patchJson($this->baseUrl("/admin/categories/{$id}"), categoryPayload([
        'image_url' => '/storage/categories/shoes-2.png',
        'is_featured' => true,
    ]))->assertOk()
        ->assertJsonPath('data.is_featured', true);

    $category = Category::query()->find($id);

    expect($category->{CategorySchema::IS_FEATURED})->toBeTrue()
        ->and($category->{CategorySchema::IMAGE_URL})->toBe('/storage/categories/shoes-2.png');
});

it('rejects a slug already taken in the same language', function () {
    $this->actingAs($this->superAdminUser());

    $this->postJson($this->baseUrl('/admin/categories'), categoryPayload())->assertCreated();

    $this->postJson($this->baseUrl('/admin/categories'), categoryPayload([
        'translations' => [[
            'language' => 'en',
            'title' => 'Other Shoes',
            'slug' => 'shoes',
            'description' => null,
        ]],
    ]))->assertStatus(422)
        ->assertJsonPath('code', 'http.422');

    expect(Category::count())->toBe(1);
});

it('nests a category under a parent', function () {
    $this->actingAs($this->superAdminUser());

    $parentId = $this->postJson($this->baseUrl('/admin/categories'), categoryPayload())
        ->assertCreated()->json('data.id');

    $child = $this->postJson($this->baseUrl('/admin/categories'), categoryPayload([
        'parent_id' => $parentId,
        'translations' => [[
            'language' => 'en',
            'title' => 'Running',
            'slug' => 'running',
            'description' => null,
        ]],
    ]))->assertCreated();

    expect((int) Category::query()->find($child->json('data.id'))->{CategorySchema::PARENT_ID})->toBe($parentId);
});
