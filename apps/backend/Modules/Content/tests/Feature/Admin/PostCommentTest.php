<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Auth\Models\User;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\PostComment\PostCommentSchema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;
use Modules\Content\Tests\Feature\HelperTrait;
use Modules\Core\Contracts\Events\Content\PostCommentApprovedEvent;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function commentablePost(): Post
{
    TestCase::defaultLanguage();

    return Post::factory()->create(['status' => 'published']);
}

it('moderates a pending comment by updating its status', function () {
    $this->actingAs($this->superAdminUser());
    $post = commentablePost();

    $comment = PostComment::factory()->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::USER_ID => User::factory()->create()->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::PENDING,
    ]);

    $this->patchJson($this->baseUrl('/admin/post-comments/'.$comment->id), [
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::PARENT_ID => null,
        PostCommentSchema::BODY => $comment->{PostCommentSchema::BODY},
        PostCommentSchema::STATUS => 'approved',
    ])->assertOk();

    expect($comment->fresh()->{PostCommentSchema::STATUS})->toBe(PostCommentStatusEnum::APPROVED);
});

it('raises the approved event only when a comment first becomes approved', function () {
    Event::fake([PostCommentApprovedEvent::class]);
    $this->actingAs($this->superAdminUser());
    $post = commentablePost();

    $comment = PostComment::factory()->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::USER_ID => User::factory()->create()->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::PENDING,
    ]);

    $payload = [
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::PARENT_ID => null,
        PostCommentSchema::BODY => $comment->{PostCommentSchema::BODY},
        PostCommentSchema::STATUS => 'approved',
    ];

    $this->patchJson($this->baseUrl('/admin/post-comments/'.$comment->id), $payload)->assertOk();

    Event::assertDispatched(PostCommentApprovedEvent::class, fn (PostCommentApprovedEvent $event) => $event->id === $comment->id
        && $event->post_id === $post->id
        && $event->status === 'approved');

    // re-saving an already-approved comment must not notify the author again
    $this->patchJson($this->baseUrl('/admin/post-comments/'.$comment->id), $payload)->assertOk();

    Event::assertDispatchedTimes(PostCommentApprovedEvent::class, 1);
});

it('filters the comment list by status and post', function () {
    $this->actingAs($this->superAdminUser());
    $post = commentablePost();
    $otherPost = commentablePost();

    PostComment::factory()->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::PENDING,
    ]);
    PostComment::factory()->create([
        PostCommentSchema::POST_ID => $otherPost->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::APPROVED,
    ]);

    $response = $this->getJson($this->baseUrl('/admin/post-comments').'?filter[status]=approved')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.status'))->toBe('approved');

    $response = $this->getJson($this->baseUrl('/admin/post-comments').'?filter[post_id]='.$post->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.post_id'))->toBe($post->id);
});

it('creates an admin reply that is approved immediately', function () {
    $admin = $this->superAdminUser();
    $this->actingAs($admin);
    $post = commentablePost();

    $parent = PostComment::factory()->create([
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::APPROVED,
    ]);

    $replyId = $this->postJson($this->baseUrl('/admin/post-comments'), [
        PostCommentSchema::POST_ID => $post->id,
        PostCommentSchema::PARENT_ID => $parent->id,
        PostCommentSchema::BODY => 'Official reply',
        PostCommentSchema::STATUS => null,
    ])->assertCreated()->json('data.id');

    $reply = PostComment::query()->find($replyId);

    expect($reply->{PostCommentSchema::STATUS})->toBe(PostCommentStatusEnum::APPROVED)
        ->and((int) $reply->{PostCommentSchema::USER_ID})->toBe($admin->id);
});
