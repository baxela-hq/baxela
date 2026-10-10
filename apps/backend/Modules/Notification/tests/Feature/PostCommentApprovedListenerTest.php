<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Content\PostCommentApprovedEvent;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function postCommentApprovedPayload(int $userId): array
{
    return [
        'id' => 12,
        'post_id' => 2,
        'user_id' => $userId,
        'parent_id' => null,
        'body' => 'Great post, thanks for the sizing tips!',
        'status' => 'approved',
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ];
}

it('notifies the author when their comment is approved', function () {
    Mail::fake();
    app()->setLocale('en');

    $author = User::factory()->create(['email' => 'reader@example.com']);

    event(PostCommentApprovedEvent::fill(postCommentApprovedPayload($author->id)));

    expect(
        Notification::query()
            ->where('user_id', $author->id)
            ->where('code', NotificationCodeEnum::CONTENT_POST_COMMENT_APPROVED->value)
            ->where('audience', 'user')
            ->where('meta->post_id', 2)
            ->where('meta->post_comment_id', 12)
            ->count()
    )->toBe(1);

    Mail::assertSent(DynamicNotification::class, 1);
});

it('renders the approval title and excerpt for the author', function () {
    Mail::fake();
    app()->setLocale('en');

    $author = User::factory()->create(['email' => 'reader@example.com']);

    event(PostCommentApprovedEvent::fill(postCommentApprovedPayload($author->id)));

    $notification = Notification::query()
        ->where('user_id', $author->id)
        ->where('code', NotificationCodeEnum::CONTENT_POST_COMMENT_APPROVED->value)
        ->first();

    expect($notification->title)->toBe('Your comment was approved')
        ->and($notification->body)->toBe('Good news — your comment "Great post, thanks for the sizing tips!" on post 2 has been approved and is now visible.');
});
