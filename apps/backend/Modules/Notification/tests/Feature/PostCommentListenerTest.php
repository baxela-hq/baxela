<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Content\PostCommentCreatedEvent;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function postCommentCreatedPayload(int $userId): array
{
    return [
        'id' => 11,
        'post_id' => 2,
        'user_id' => $userId,
        'parent_id' => null,
        'body' => 'Great post, thanks for the sizing tips!',
        'status' => 'pending',
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ];
}

it('notifies admins when a user comments on a post', function () {
    Mail::fake();

    $admin = $this->superAdminUser();
    $reader = User::factory()->create();

    event(PostCommentCreatedEvent::fill(postCommentCreatedPayload($reader->id)));

    expect(
        Notification::query()
            ->where('user_id', $admin->id)
            ->where('code', NotificationCodeEnum::CONTENT_POST_COMMENT_CREATED->value)
            ->where('audience', 'admin')
            ->where('meta->post_id', 2)
            ->where('meta->post_comment_id', 11)
            ->count()
    )->toBe(1);

    Mail::assertSent(DynamicNotification::class, 1);
});

it('renders the pending-comment title and excerpt into the notification body', function () {
    Mail::fake();
    app()->setLocale('en');

    $admin = $this->superAdminUser();
    $reader = User::factory()->create();

    event(PostCommentCreatedEvent::fill(postCommentCreatedPayload($reader->id)));

    $notification = Notification::query()
        ->where('user_id', $admin->id)
        ->where('code', NotificationCodeEnum::CONTENT_POST_COMMENT_CREATED->value)
        ->first();

    expect($notification->title)->toBe('New comment pending approval')
        ->and($notification->body)->toBe('A new comment on post 2 awaits moderation: "Great post, thanks for the sizing tips!"');
});
