<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Content\Database\Seeders\ContentDatabaseSeeder;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Tests\Feature\HelperTrait;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Media\Database\Seeders\MediaDatabaseSeeder;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('seeds posts and post categories with translations', function () {
    $this->seed(CoreDatabaseSeeder::class);
    // post cover photos upsert into the Content/Post media folder
    $this->seed(MediaDatabaseSeeder::class);
    $this->seed(ContentDatabaseSeeder::class);

    $posts = Post::query()->get();
    $categories = PostCategory::query()->get();

    // two categories and four posts come from the seeder lang files
    expect($posts)->toHaveCount(4)
        ->and($categories)->toHaveCount(2);

    $welcome = $posts->first(
        fn (Post $post) => $post->translations->pluck(PTSchema::SLUG)->contains('welcome-to-baxela')
    );
    expect($welcome)->not->toBeNull()
        ->and($welcome->{PostSchema::STATUS})->toBe(PostStatusEnum::PUBLISHED)
        // posts seed with a translation per active language (en + fa)
        ->and($welcome->translations)->toHaveCount(2)
        // the welcome post is attached to the news category
        ->and($welcome->categories()->count())->toBe(1)
        // each seeded post carries its cover photo on the public disk
        ->and($welcome->{PostSchema::IMAGE_URL})->toContain('welcome-to-baxela.jpg')
        ->and($welcome->{PostSchema::IMAGE_MEDIA_ID})->not->toBeNull();
});
