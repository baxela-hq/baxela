<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function postCategory(string $slug = 'news'): PostCategory
{
    $category = PostCategory::factory()->create();

    $category->translations()->create([
        'language_id' => TestCase::defaultLanguage()->id,
        'title' => 'News',
        'slug' => $slug,
    ]);

    return $category;
}

function postPayload(array $overrides = []): array
{
    TestCase::defaultLanguage();

    return array_merge([
        'status' => 'published',
        'is_featured' => false,
        'categories' => null,
        'translations' => [[
            'language' => 'en',
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'content' => 'The post content',
            'description' => null,
        ]],
    ], $overrides);
}

it('creates a post with translations and categories as an admin', function () {
    $this->actingAs($this->superAdminUser());
    $category = postCategory();

    $postId = $this->postJson($this->baseUrl('/admin/posts'), postPayload([
        'categories' => [$category->id],
    ]))
        ->assertCreated()
        ->json('data.id');

    $post = Post::query()->find($postId);

    expect($post)->not->toBeNull()
        ->and($post->translations()->count())->toBe(1)
        ->and($post->categories()->pluck(PostCategorySchema::ID)->all())->toBe([$category->id])
        ->and($post->{PostSchema::IS_FEATURED})->toBeFalse();
});

it('updates a post and syncs its categories', function () {
    $this->actingAs($this->superAdminUser());
    $category = postCategory();
    $otherCategory = postCategory('guides');

    $postId = $this->postJson($this->baseUrl('/admin/posts'), postPayload([
        'categories' => [$category->id],
    ]))->assertCreated()->json('data.id');

    // slug must change on update: same-slug re-saves trip the per-language
    // unique guard, so refreshes always carry a new slug
    $this->patchJson($this->baseUrl('/admin/posts/'.$postId), postPayload([
        'status' => 'draft',
        'is_featured' => true,
        'categories' => [$otherCategory->id],
        'translations' => [[
            'language' => 'en',
            'title' => 'Hello World v2',
            'slug' => 'hello-world-v2',
            'content' => 'Rewritten content',
            'description' => null,
        ]],
    ]))->assertOk();

    $post = Post::query()->find($postId);

    expect($post->categories()->pluck(PostCategorySchema::ID)->all())->toBe([$otherCategory->id])
        ->and($post->{PostSchema::STATUS}->value)->toBe('draft')
        ->and($post->{PostSchema::IS_FEATURED})->toBeTrue()
        ->and($post->translations()->first()->title)->toBe('Hello World v2');
});

it('rejects a slug already taken in the same language', function () {
    $this->actingAs($this->superAdminUser());

    $this->postJson($this->baseUrl('/admin/posts'), postPayload())->assertCreated();

    $this->postJson($this->baseUrl('/admin/posts'), postPayload([
        'translations' => [[
            'language' => 'en',
            'title' => 'Other Post',
            'slug' => 'hello-world',
            'content' => 'Other content',
            'description' => null,
        ]],
    ]))->assertStatus(422)->assertJsonPath('code', 'http.422');

    expect(Post::count())->toBe(1);
});

it('deletes a post', function () {
    $this->actingAs($this->superAdminUser());

    $postId = $this->postJson($this->baseUrl('/admin/posts'), postPayload())
        ->assertCreated()->json('data.id');

    $this->deleteJson($this->baseUrl('/admin/posts/'.$postId))
        ->assertStatus(204);

    expect(Post::count())->toBe(0);
});

it('rejects unauthenticated admin access', function () {
    TestCase::defaultLanguage();

    $this->getJson($this->baseUrl('/admin/posts'))
        ->assertStatus(401);
});
