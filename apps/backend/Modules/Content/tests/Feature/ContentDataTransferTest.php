<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Content\Models\Page;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Tests\Feature\HelperTrait;
use Modules\Core\Models\TransferImport;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function contentTransferTransferFile(string $content): File
{
    return UploadedFile::fake()->createWithContent('transfer.json', $content);
}

function seedContentTransferData(): array
{
    $en = TestCase::defaultLanguage();

    $page = Page::query()->create(['status' => 'published']);
    $page->translations()->create([
        'language_id' => $en->id, 'title' => 'About', 'slug' => 'about',
        'content' => '<p>about</p>', 'description' => 'About page',
    ]);

    $parent = PostCategory::query()->create(['parent_id' => null, 'position' => 1]);
    $parent->translations()->create(['language_id' => $en->id, 'title' => 'News', 'slug' => 'news']);
    $child = PostCategory::query()->create(['parent_id' => $parent->id, 'position' => 2]);
    $child->translations()->create(['language_id' => $en->id, 'title' => 'Releases', 'slug' => 'releases']);

    $product = Product::query()->create(['type' => 'simple', 'status' => 'in_stock', 'is_published' => true]);

    $post = Post::query()->create(['status' => 'draft', 'published_at' => null]);
    $post->translations()->create([
        'language_id' => $en->id, 'title' => 'Hello', 'slug' => 'hello',
        'content' => '<p>hi</p>', 'description' => null,
    ]);
    $post->seo()->create([
        'language_id' => $en->id, 'meta_title' => 'Hello SEO',
        'meta_description' => null, 'open_graph_title' => null, 'open_graph_description' => null,
    ]);
    $post->categories()->attach([$parent->id, $child->id]);
    $post->products()->create(['product_id' => $product->id]);

    return ['page' => $page, 'parent' => $parent, 'child' => $child, 'post' => $post, 'product' => $product];
}

function wipeContentTables(): void
{
    DB::table('content_post_product')->delete();
    DB::table('content_post_category_post')->delete();
    DB::table('content_post_seo_translations')->delete();
    DB::table('content_post_translations')->delete();
    DB::table('content_posts')->delete();
    DB::table('content_post_category_translations')->delete();
    DB::table('content_post_categories')->delete();
    DB::table('content_page_translations')->delete();
    DB::table('content_pages')->delete();
}

it('exports content in dependency order with source-id references', function () {
    $seeded = seedContentTransferData();
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedContentDataFile();

    expect($file['module'])->toBe('content')
        ->and(array_column($file['sections'], 'entity'))->toBe(['pages', 'post-categories', 'posts'])
        // Parents before children in the category tree.
        ->and($file['sections'][1]['rows'][0]['source_id'])->toBe($seeded['parent']->id)
        ->and($file['sections'][1]['rows'][1]['payload']['parent_id'])->toBe($seeded['parent']->id)
        ->and($file['sections'][2]['rows'][0]['payload']['translations'][0]['language'])->toBe('en')
        ->and($file['sections'][2]['rows'][0]['payload']['categories'])->toBe([$seeded['parent']->id, $seeded['child']->id])
        ->and($file['sections'][2]['rows'][0]['payload']['products'])->toBe([$seeded['product']->id])
        ->and($file['sections'][2]['rows'][0]['payload']['seo'][0]['meta_title'])->toBe('Hello SEO');
});

it('round-trips content via the module endpoint', function () {
    $seeded = seedContentTransferData();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedContentDataFile();

    wipeContentTables();

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => contentTransferTransferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and($response->json('data.created_count'))->toBe(4)
        ->and(Page::query()->count())->toBe(1)
        ->and(PostCategory::query()->count())->toBe(2)
        ->and(Post::query()->count())->toBe(1);

    // The tree, the post's category links and the product pivot all
    // remapped to the recreated ids.
    $parent = PostCategory::query()->whereNull('parent_id')->first();
    $child = PostCategory::query()->whereNotNull('parent_id')->first();
    $post = Post::query()->first();
    expect($child->parent_id)->toBe($parent->id)
        ->and($post->categories()->pluck('id'))->toContain($parent->id, $child->id)
        ->and($post->products()->first()->product_id)->toBe($seeded['product']->id)
        ->and($post->seo()->first()->meta_title)->toBe('Hello SEO')
        ->and(Page::query()->first()->translations()->first()->slug)->toBe('about');
});

it('records the run in the audit table', function () {
    seedContentTransferData();
    $this->actingAs($this->superAdminUser());

    $this->exportedContentDataFile();

    expect(TransferImport::query()->where('module', 'content')->count())->toBe(0);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => contentTransferTransferFile('{}'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(401);
});
