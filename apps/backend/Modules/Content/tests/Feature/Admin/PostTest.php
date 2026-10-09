<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\Post\PostImageSchema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostSeoTranslationSchema;
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
        'categories' => null,
        'images' => null,
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
        ->and($post->categories()->pluck(PostCategorySchema::ID)->all())->toBe([$category->id]);
});

it('attaches images to a post and replaces them on update', function () {
    $this->actingAs($this->superAdminUser());

    $postId = $this->postJson($this->baseUrl('/admin/posts'), postPayload([
        'images' => [
            ['media_id' => 11, 'url' => 'https://cdn.test/cover.jpg', 'collection' => 'photos', 'position' => 1],
            ['media_id' => 12, 'url' => 'https://cdn.test/gallery.jpg', 'collection' => 'photos', 'position' => 2],
        ],
    ]))
        ->assertCreated()
        ->assertJsonCount(2, 'data.images')
        ->json('data.id');

    $post = Post::query()->find($postId);

    expect($post->images()->count())->toBe(2)
        ->and($post->images()->pluck(PostImageSchema::MEDIA_ID)->all())->toBe([11, 12]);

    $this->patchJson($this->baseUrl('/admin/posts/'.$postId), postPayload([
        'images' => [
            ['media_id' => 13, 'url' => 'https://cdn.test/new-cover.jpg', 'collection' => 'photos', 'position' => 1],
        ],
        'translations' => [[
            'language' => 'en',
            'title' => 'Hello World v2',
            'slug' => 'hello-world-v2',
            'content' => 'Rewritten content',
            'description' => null,
        ]],
    ]))->assertOk()->assertJsonCount(1, 'data.images');

    expect($post->images()->count())->toBe(1)
        ->and($post->images()->first()->{PostImageSchema::MEDIA_ID})->toBe(13);
});

it('stores seo per language, normalizing empty fields to null', function () {
    $this->actingAs($this->superAdminUser());

    $postId = $this->postJson($this->baseUrl('/admin/posts'), postPayload([
        'seo' => [
            ['language' => 'en', 'meta_title' => 'Welcome', 'meta_description' => '', 'open_graph_title' => null, 'open_graph_description' => null],
        ],
    ]))
        ->assertCreated()
        ->assertJsonCount(1, 'data.seo')
        ->assertJsonPath('data.seo.0.meta_title', 'Welcome')
        ->assertJsonPath('data.seo.0.meta_description', null)
        ->json('data.id');

    $post = Post::query()->find($postId);

    expect($post->seo()->count())->toBe(1)
        ->and($post->seo()->first()->{PostSeoTranslationSchema::META_TITLE})->toBe('Welcome')
        ->and($post->seo()->first()->{PostSeoTranslationSchema::META_DESCRIPTION})->toBeNull();
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

it('schedules a post by storing and clearing a publish date', function () {
    $this->actingAs($this->superAdminUser());

    $postId = $this->postJson($this->baseUrl('/admin/posts'), postPayload([
        'published_at' => now()->addDay()->toIso8601String(),
    ]))->assertCreated()->json('data.id');

    $post = Post::query()->find($postId);

    expect($post->{PostSchema::PUBLISHED_AT}->getTimestamp())
        ->toBe(now()->addDay()->getTimestamp());

    // slug must change on update: same-slug re-saves trip the per-language
    // unique guard, so refreshes always carry a new slug
    $this->patchJson($this->baseUrl('/admin/posts/'.$postId), postPayload([
        'published_at' => null,
        'translations' => [[
            'language' => 'en',
            'title' => 'Hello World v2',
            'slug' => 'hello-world-v2',
            'content' => 'Rewritten content',
            'description' => null,
        ]],
    ]))->assertOk();

    expect($post->fresh()->{PostSchema::PUBLISHED_AT})->toBeNull();
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
