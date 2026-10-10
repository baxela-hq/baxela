<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Content\Models\Page;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;
use Modules\Content\Schemas\Page\PageTranslationSchema as PagTSchema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;
use Modules\Content\Tests\Feature\HelperTrait;
use Modules\Core\Models\Language;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function sitemapPost(string $slug, array $attributes = []): Post
{
    $post = Post::factory()->create([
        PostSchema::STATUS => PostStatusEnum::PUBLISHED,
        ...$attributes,
    ]);

    $post->translations()->create([
        PTSchema::LANGUAGE_ID => TestCase::defaultLanguage()->id,
        PTSchema::TITLE => 'Post '.$slug,
        PTSchema::SLUG => $slug,
        PTSchema::CONTENT => 'Content',
    ]);

    return $post;
}

function sitemapPage(string $slug, array $attributes = []): Page
{
    $page = Page::factory()->create([
        PageSchema::STATUS => PageStatusEnum::PUBLISHED,
        ...$attributes,
    ]);

    $page->translations()->create([
        PagTSchema::LANGUAGE_ID => TestCase::defaultLanguage()->id,
        PagTSchema::TITLE => 'Page '.$slug,
        PagTSchema::SLUG => $slug,
        PagTSchema::CONTENT => 'Content',
    ]);

    return $page;
}

function sitemapPostCategory(string $slug): PostCategory
{
    $category = PostCategory::factory()->create();

    $category->translations()->create([
        PCTSchema::LANGUAGE_ID => TestCase::defaultLanguage()->id,
        PCTSchema::TITLE => 'Category '.$slug,
        PCTSchema::SLUG => $slug,
    ]);

    return $category;
}

it('feeds published posts, all post categories and published pages', function () {
    TestCase::defaultLanguage();

    $post = sitemapPost('published-post');
    $category = sitemapPostCategory('news');
    $page = sitemapPage('about');

    $this->getJson($this->baseUrl('/public/sitemap'))
        ->assertOk()
        ->assertJsonCount(1, 'data.posts')
        ->assertJsonPath('data.posts.0.id', $post->id)
        ->assertJsonCount(1, 'data.post_categories')
        ->assertJsonPath('data.post_categories.0.id', $category->id)
        ->assertJsonCount(1, 'data.pages')
        ->assertJsonPath('data.pages.0.id', $page->id)
        ->assertJsonStructure([
            'data' => [
                'posts' => [['id', 'updated_at', 'translations' => [['language', 'slug']]]],
                'post_categories' => [['id', 'updated_at', 'translations' => [['language', 'slug']]]],
                'pages' => [['id', 'updated_at', 'translations' => [['language', 'slug']]]],
            ],
        ]);
});

it('hides drafts and future-scheduled posts from the feed', function () {
    TestCase::defaultLanguage();

    sitemapPost('draft-post', [PostSchema::STATUS => PostStatusEnum::DRAFT]);
    sitemapPost('scheduled-post', [PostSchema::PUBLISHED_AT => now()->addDay()]);
    sitemapPage('draft-page', [PageSchema::STATUS => PageStatusEnum::DRAFT]);

    $this->getJson($this->baseUrl('/public/sitemap'))
        ->assertOk()
        ->assertJsonCount(0, 'data.posts')
        ->assertJsonCount(0, 'data.pages');
});

it('exposes per-language slugs on every entry', function () {
    TestCase::defaultLanguage();
    $fa = Language::query()->firstOrCreate(
        ['code' => 'fa'],
        ['locale' => 'fa', 'name' => 'Persian', 'native_name' => 'فارسی', 'code3' => 'fas', 'is_rtl' => true, 'is_active' => true],
    );

    $post = sitemapPost('english-post');
    $post->translations()->create([
        PTSchema::LANGUAGE_ID => $fa->id,
        PTSchema::TITLE => 'نوشته',
        PTSchema::SLUG => 'persian-post',
        PTSchema::CONTENT => 'محتوا',
    ]);

    $this->getJson($this->baseUrl('/public/sitemap'))
        ->assertOk()
        ->assertJsonCount(2, 'data.posts.0.translations')
        ->assertJsonPath('data.posts.0.translations.0.language', 'en')
        ->assertJsonPath('data.posts.0.translations.0.slug', 'english-post')
        ->assertJsonPath('data.posts.0.translations.1.language', 'fa')
        ->assertJsonPath('data.posts.0.translations.1.slug', 'persian-post');
});
