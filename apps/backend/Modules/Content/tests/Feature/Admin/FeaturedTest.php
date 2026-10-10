<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Content\Models\FeaturedItem;
use Modules\Content\Models\Post;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function featuredPost(string $slug): Post
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

it('lists the featured posts ordered by position', function () {
    $second = featuredPost('second-post');
    $first = featuredPost('first-post');

    FeaturedItem::query()->create([
        FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_POST,
        FeaturedItemSchema::FEATUREDABLE_ID => $second->id,
        FeaturedItemSchema::POSITION => 2,
    ]);
    FeaturedItem::query()->create([
        FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_POST,
        FeaturedItemSchema::FEATUREDABLE_ID => $first->id,
        FeaturedItemSchema::POSITION => 1,
    ]);

    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/featured'))
        ->assertOk();

    expect(collect($response->json('data.post'))->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($response->json('data.post.0.translations'))->toBeArray();
});

it('syncs the featured selection, array order encoding position', function () {
    $a = featuredPost('a');
    $b = featuredPost('b');
    $this->actingAs($this->superAdminUser());

    $this->putJson($this->baseUrl('/admin/featured'), [
        'post_ids' => [$b->id, $a->id],
    ])->assertOk();

    $positions = FeaturedItem::query()
        ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_POST)
        ->pluck(FeaturedItemSchema::POSITION, FeaturedItemSchema::FEATUREDABLE_ID);
    expect($positions[$b->id])->toBe(1)
        ->and($positions[$a->id])->toBe(2);

    // A second sync fully replaces the previous selection.
    $this->putJson($this->baseUrl('/admin/featured'), [
        'post_ids' => [$a->id],
    ])->assertOk();

    expect(FeaturedItem::query()->count())->toBe(1)
        ->and(FeaturedItem::query()->value(FeaturedItemSchema::FEATUREDABLE_ID))->toBe($a->id);

    // The response mirrors the persisted selection.
    $response = $this->getJson($this->baseUrl('/admin/featured'))->assertOk();
    expect(collect($response->json('data.post'))->pluck('id')->all())->toBe([$a->id]);
});

it('rejects unknown or duplicate ids', function () {
    $post = featuredPost('kept');
    $this->actingAs($this->superAdminUser());

    $this->putJson($this->baseUrl('/admin/featured'), [
        'post_ids' => [$post->id, 999999],
    ])->assertStatus(422);

    $this->putJson($this->baseUrl('/admin/featured'), [
        'post_ids' => [$post->id, $post->id],
    ])->assertStatus(422);

    // The key is required: a missing payload must not wipe the selection.
    $this->putJson($this->baseUrl('/admin/featured'), [])->assertStatus(422);

    expect(FeaturedItem::query()->count())->toBe(0);
});

it('removes the featured row when a post is deleted', function () {
    $post = featuredPost('doomed');
    FeaturedItem::query()->create([
        FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_POST,
        FeaturedItemSchema::FEATUREDABLE_ID => $post->id,
        FeaturedItemSchema::POSITION => 1,
    ]);
    $this->actingAs($this->superAdminUser());

    $this->deleteJson($this->baseUrl('/admin/posts/'.$post->id))
        ->assertNoContent();

    expect(FeaturedItem::query()->count())->toBe(0);
});

it('denies the featured endpoints without permission', function () {
    TestCase::defaultLanguage();
    $this->actingAs(User::factory()->create());

    $this->getJson($this->baseUrl('/admin/featured'))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');

    $this->putJson($this->baseUrl('/admin/featured'), [
        'post_ids' => [],
    ])->assertStatus(403)->assertJsonPath('code', 'http.403');
});
