<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\PostComment\PostCommentSchema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;
use Modules\Content\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('lets an authenticated user comment on a post, stored as pending', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $post = Post::factory()->create(['status' => 'published']);

    $response = $this->postJson($this->baseUrl('/user/posts/'.$post->id.'/comments'), [
        PostCommentSchema::BODY => 'Great article!',
        PostCommentSchema::PARENT_ID => null,
    ])->assertCreated();

    $comment = PostComment::query()->find($response->json('data.id'));

    expect($comment)->not->toBeNull()
        ->and($comment->{PostCommentSchema::BODY})->toBe('Great article!')
        ->and((int) $comment->{PostCommentSchema::USER_ID})->toBe($user->id)
        // moderation gate: nothing is public until an admin approves
        ->and($comment->{PostCommentSchema::STATUS})->toBe(PostCommentStatusEnum::PENDING);
});

it('rejects guest comments with 401', function () {
    $post = Post::factory()->create(['status' => 'published']);

    $this->postJson($this->baseUrl('/user/posts/'.$post->id.'/comments'), [
        PostCommentSchema::BODY => 'Great article!',
        PostCommentSchema::PARENT_ID => null,
    ])->assertStatus(401);
});

it('rejects an invalid comment payload', function () {
    $this->actingAs(User::factory()->create());
    $post = Post::factory()->create(['status' => 'published']);

    $this->postJson($this->baseUrl('/user/posts/'.$post->id.'/comments'), [
        PostCommentSchema::BODY => null,
        PostCommentSchema::PARENT_ID => null,
    ])->assertStatus(422)->assertJsonPath('code', 'http.422');

    expect(PostComment::count())->toBe(0);
});

it('rejects a parent comment from another post', function () {
    $this->actingAs(User::factory()->create());

    $post = Post::factory()->create(['status' => 'published']);
    $otherPost = Post::factory()->create(['status' => 'published']);

    $parent = PostComment::factory()->create([
        PostCommentSchema::POST_ID => $otherPost->id,
        PostCommentSchema::STATUS => PostCommentStatusEnum::APPROVED,
    ]);

    $this->postJson($this->baseUrl('/user/posts/'.$post->id.'/comments'), [
        PostCommentSchema::BODY => 'Reply attempt',
        PostCommentSchema::PARENT_ID => $parent->id,
    ])->assertStatus(400)->assertJsonPath('code', 'content.post_comment.invalid_parent');
});
