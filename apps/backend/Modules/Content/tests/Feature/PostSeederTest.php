<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Content\Database\Seeders\ContentDatabaseSeeder;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Tests\Feature\HelperTrait;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('seeds posts and post categories with translations', function () {
    $this->seed(CoreDatabaseSeeder::class);
    $this->seed(ContentDatabaseSeeder::class);

    $posts = Post::query()->get();
    $categories = PostCategory::query()->get();

    // two categories and two posts come from the seeder lang files
    expect($posts)->toHaveCount(2)
        ->and($categories)->toHaveCount(2);

    $featured = $posts->firstWhere(PostSchema::IS_FEATURED, true);
    expect($featured)->not->toBeNull()
        ->and($featured->{PostSchema::STATUS})->toBe(PostStatusEnum::PUBLISHED)
        // posts seed with a translation per active language (en + fa)
        ->and($featured->translations)->toHaveCount(2)
        // the welcome post is attached to the news category
        ->and($featured->categories()->count())->toBe(1);
});
