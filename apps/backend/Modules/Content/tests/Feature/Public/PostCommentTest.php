<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\PostComment\PostCommentSchema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;
use Modules\Content\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function commentedPost(): Post
{
    return Post::factory()->create(['status' => 'published']);
}

it('lists only approved top-level comments with approved replies', function () {
    $post = commentedPost();

    $approved = PostComment::factory()->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::APPROVED,
    ]);
    PostComment::factory()->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::PENDING,
    ]);
    PostComment::factory()->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::REJECTED,
    ]);

    $approvedReply = PostComment::factory()->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::PARENT_ID => $approved->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::APPROVED,
    ]);
    PostComment::factory()->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::PARENT_ID => $approved->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::PENDING,
    ]);

    $response = $this->getJson($this->baseUrl('/public/posts/'.$post->id.'/comments'))
        ->assertOk();

    // only the approved top-level comment is listed, not the pending/rejected ones
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($approved->id)
        // replies are nested, and only the approved one is exposed
        ->and($response->json('data.0.replies'))->toHaveCount(1)
        ->and($response->json('data.0.replies.0.id'))->toBe($approvedReply->id);
});

it('caps an unbounded per_page on the public post comments endpoint', function () {
    $post = commentedPost();

    PostComment::factory()->count(3)->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::APPROVED,
    ]);

    $this->getJson($this->baseUrl('/public/posts/'.$post->id.'/comments').'?per_page=1000000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});
