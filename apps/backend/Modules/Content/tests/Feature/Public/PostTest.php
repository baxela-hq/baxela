<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\Post\PostProductSchema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function publishedPost(string $slug): Post
{
    $post = Post::factory()->create([PostSchema::STATUS => PostStatusEnum::PUBLISHED]);

    $post->translations()->create([
        PTSchema::LANGUAGE_ID => TestCase::defaultLanguage()->id,
        PTSchema::TITLE => 'Post '.$slug,
        PTSchema::SLUG => $slug,
        PTSchema::CONTENT => 'Content',
    ]);

    return $post;
}

function scheduledPost(string $slug, mixed $publishedAt): Post
{
    $post = Post::factory()->create([
        PostSchema::STATUS => PostStatusEnum::PUBLISHED,
        PostSchema::PUBLISHED_AT => $publishedAt,
    ]);

    $post->translations()->create([
        PTSchema::LANGUAGE_ID => TestCase::defaultLanguage()->id,
        PTSchema::TITLE => 'Post '.$slug,
        PTSchema::SLUG => $slug,
        PTSchema::CONTENT => 'Content',
    ]);

    return $post;
}

it('lists only published posts publicly', function () {
    TestCase::defaultLanguage();

    publishedPost('published-post');
    Post::factory()->create([PostSchema::STATUS => PostStatusEnum::DRAFT]);

    $this->getJson($this->baseUrl('/public/posts'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'published-post');
});

it('filters public posts by category slug', function () {
    TestCase::defaultLanguage();

    $category = PostCategory::factory()->create();
    $category->translations()->create([
        'language_id' => TestCase::defaultLanguage()->id,
        'title' => 'News',
        'slug' => 'news',
    ]);

    $categorized = publishedPost('categorized-post');
    $categorized->categories()->attach($category->id);
    publishedPost('uncategorized-post');

    $this->getJson($this->baseUrl('/public/posts').'?category=news')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'categorized-post');
});

it('hides a post scheduled for the future until its publish moment', function () {
    TestCase::defaultLanguage();

    $scheduled = scheduledPost('scheduled-post', now()->addDay());
    // a draft with a past publish date must stay hidden too
    Post::factory()->create([
        PostSchema::STATUS => PostStatusEnum::DRAFT,
        PostSchema::PUBLISHED_AT => now()->subDay(),
    ]);

    $this->getJson($this->baseUrl('/public/posts'))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson($this->baseUrl('/public/posts/scheduled-post'))
        ->assertStatus(404);

    $this->getJson($this->baseUrl('/public/posts/'.$scheduled->id))
        ->assertStatus(404);

    // once the publish moment passes, the post goes live on its own
    $this->travelTo(now()->addDay()->addMinute());

    $this->getJson($this->baseUrl('/public/posts'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'scheduled-post');

    $this->getJson($this->baseUrl('/public/posts/scheduled-post'))
        ->assertOk()
        ->assertJsonPath('data.slug', 'scheduled-post');
});

it('shows posts with a past publish date immediately', function () {
    TestCase::defaultLanguage();

    scheduledPost('backdated-post', now()->subDay());

    $this->getJson($this->baseUrl('/public/posts'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'backdated-post');
});

it('shows a published post by slug or id', function () {
    $post = publishedPost('published-post');

    $this->getJson($this->baseUrl('/public/posts/published-post'))
        ->assertOk()
        ->assertJsonPath('data.slug', 'published-post');

    $this->getJson($this->baseUrl('/public/posts/'.$post->id))
        ->assertOk()
        ->assertJsonPath('data.id', $post->id);
});

it('lists the related products of a published post', function () {
    TestCase::defaultLanguage();

    $product = Product::factory()->create();
    $product->translations()->create([
        'language_id' => TestCase::defaultLanguage()->id,
        'title' => 'Sneaker',
        'slug' => 'sneaker',
        'content' => 'Soft',
    ]);

    $post = publishedPost('post-with-products');
    $post->products()->create([PostProductSchema::PRODUCT_ID => $product->id]);

    $this->getJson($this->baseUrl('/public/posts/post-with-products'))
        ->assertOk()
        ->assertJsonCount(1, 'data.products')
        ->assertJsonPath('data.products.0.id', $product->id)
        ->assertJsonPath('data.products.0.title', 'Sneaker');
});

it('returns 404 for an unknown or draft slug', function () {
    TestCase::defaultLanguage();

    $this->getJson($this->baseUrl('/public/posts/no-such-post'))
        ->assertStatus(404)
        ->assertJsonPath('code', 'http.404');

    $draft = Post::factory()->create([PostSchema::STATUS => PostStatusEnum::DRAFT]);
    $draft->translations()->create([
        PTSchema::LANGUAGE_ID => TestCase::defaultLanguage()->id,
        PTSchema::TITLE => 'Draft',
        PTSchema::SLUG => 'draft-post',
        PTSchema::CONTENT => 'Content',
    ]);

    $this->getJson($this->baseUrl('/public/posts/draft-post'))
        ->assertStatus(404);
});

it('lists public post categories and shows one by slug', function () {
    TestCase::defaultLanguage();

    $category = PostCategory::factory()->create(['position' => 1]);
    $category->translations()->create([
        'language_id' => TestCase::defaultLanguage()->id,
        'title' => 'News',
        'slug' => 'news',
    ]);

    $this->getJson($this->baseUrl('/public/post-categories'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'news');

    $this->getJson($this->baseUrl('/public/post-categories/news'))
        ->assertOk()
        ->assertJsonPath('data.slug', 'news');
});
