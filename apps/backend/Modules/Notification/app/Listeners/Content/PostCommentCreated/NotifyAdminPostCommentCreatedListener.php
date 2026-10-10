<?php

namespace Modules\Notification\Listeners\Content\PostCommentCreated;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Events\Content\PostCommentCreatedEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AdminRecipients;

class NotifyAdminPostCommentCreatedListener implements ShouldQueue
{
    use Queueable;

    /**
     * Create the event listener.
     */
    public function __construct(private readonly NotificationDispatcherInterface $notificationDispatcher) {}

    /**
     * Handle the event.
     */
    public function handle(PostCommentCreatedEvent $event): void
    {
        $data = [
            'post_id' => $event->post_id,
            'comment_id' => $event->id,
            'excerpt' => Str::limit($event->body, 160),
        ];

        $message = new NotificationMessage(
            code: NotificationCodeEnum::CONTENT_POST_COMMENT_CREATED->value,
            audience: 'admin',
            recipients: [
                'email' => config('notification.notifications.admin_recipients.email', []),
                'database' => AdminRecipients::databaseIds(),
            ],
            data: [
                'database' => $data,
                'email' => $data,
            ],
            meta: ['post_id' => $event->post_id, 'post_comment_id' => $event->id],
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
