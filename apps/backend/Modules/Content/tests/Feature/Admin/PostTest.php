<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\Post\PostProductSchema;
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

it('attaches related products to a post and syncs them on update', function () {
    $this->actingAs($this->superAdminUser());

    $first = Product::factory()->create();
    $second = Product::factory()->create();
    $third = Product::factory()->create();

    $postId = $this->postJson($this->baseUrl('/admin/posts'), postPayload([
        'products' => [$first->id, $second->id],
    ]))
        ->assertCreated()
        ->assertJsonCount(2, 'data.products')
        ->json('data.id');

    $post = Post::query()->find($postId);

    expect($post->products()->pluck(PostProductSchema::PRODUCT_ID)->all())
        ->toBe([$first->id, $second->id]);

    // slug must change on update: same-slug re-saves trip the per-language
    // unique guard, so refreshes always carry a new slug
    $this->patchJson($this->baseUrl('/admin/posts/'.$postId), postPayload([
        'products' => [$third->id],
        'translations' => [[
            'language' => 'en',
            'title' => 'Hello World v2',
            'slug' => 'hello-world-v2',
            'content' => 'Rewritten content',
            'description' => null,
        ]],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.products')
        ->assertJsonPath('data.products.0.id', $third->id);

    expect($post->products()->pluck(PostProductSchema::PRODUCT_ID)->all())
        ->toBe([$third->id]);

    // product ids are validated through the Catalog gateway
    $this->postJson($this->baseUrl('/admin/posts'), postPayload([
        'products' => [999999],
    ]))->assertStatus(422)->assertJsonPath('code', 'http.422');
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
