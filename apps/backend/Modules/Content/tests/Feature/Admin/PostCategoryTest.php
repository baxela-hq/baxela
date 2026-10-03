<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function postCategoryPayload(array $overrides = []): array
{
    TestCase::defaultLanguage();

    return array_merge([
        'parent_id' => null,
        'position' => null,
        'translations' => [[
            'language' => 'en',
            'title' => 'News',
            'slug' => 'news',
            'description' => null,
        ]],
    ], $overrides);
}

it('creates a post category with translations as an admin', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->postJson($this->baseUrl('/admin/post-categories'), postCategoryPayload())
        ->assertCreated();

    $category = PostCategory::query()->find($response->json('data.id'));

    expect($category)->not->toBeNull()
        ->and($category->translations()->count())->toBe(1)
        ->and($category->translations()->first()->title)->toBe('News');
});

it('nests a post category under a parent', function () {
    $this->actingAs($this->superAdminUser());

    $parentId = $this->postJson($this->baseUrl('/admin/post-categories'), postCategoryPayload())
        ->assertCreated()->json('data.id');

    $child = $this->postJson($this->baseUrl('/admin/post-categories'), postCategoryPayload([
        'parent_id' => $parentId,
        'position' => 1,
        'translations' => [[
            'language' => 'en',
            'title' => 'Company News',
            'slug' => 'company-news',
            'description' => null,
        ]],
    ]))->assertCreated();

    $category = PostCategory::query()->find($child->json('data.id'));

    expect((int) $category->{PostCategorySchema::PARENT_ID})->toBe($parentId)
        ->and($category->{PostCategorySchema::POSITION})->toBe(1);
});

it('rejects a slug already taken in the same language', function () {
    $this->actingAs($this->superAdminUser());

    $this->postJson($this->baseUrl('/admin/post-categories'), postCategoryPayload())->assertCreated();

    $this->postJson($this->baseUrl('/admin/post-categories'), postCategoryPayload([
        'translations' => [[
            'language' => 'en',
            'title' => 'Other News',
            'slug' => 'news',
            'description' => null,
        ]],
    ]))->assertStatus(422)
        ->assertJsonPath('code', 'http.422');

    expect(PostCategory::count())->toBe(1);
});

it('lists post categories ordered by position with nulls last', function () {
    $this->actingAs($this->superAdminUser());
    TestCase::defaultLanguage();

    $unpositioned = PostCategory::factory()->create(['position' => null]);
    $second = PostCategory::factory()->create(['position' => 2]);
    $first = PostCategory::factory()->create(['position' => 1]);

    $response = $this->getJson($this->baseUrl('/admin/post-categories'))
        ->assertOk();

    expect($response->json('data.0.id'))->toBe($first->id)
        ->and($response->json('data.1.id'))->toBe($second->id)
        ->and($response->json('data.2.id'))->toBe($unpositioned->id);
});
