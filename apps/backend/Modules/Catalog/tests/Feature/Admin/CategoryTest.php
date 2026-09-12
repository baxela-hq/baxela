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
