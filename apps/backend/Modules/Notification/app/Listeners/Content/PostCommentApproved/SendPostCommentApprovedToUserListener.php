<?php

namespace Modules\Notification\Listeners\Content\PostCommentApproved;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Events\Content\PostCommentApprovedEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class SendPostCommentApprovedToUserListener implements ShouldQueue
{
    use Queueable;

    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly AccessGatewayInterface $accessGateway,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PostCommentApprovedEvent $event): void
    {
        $email = $this->accessGateway->getUserEmailsByIds([$event->user_id])[$event->user_id] ?? null;

        $data = [
            'post_id' => $event->post_id,
            'excerpt' => Str::limit($event->body, 160),
        ];

        $message = new NotificationMessage(
            code: NotificationCodeEnum::CONTENT_POST_COMMENT_APPROVED->value,
            audience: 'user',
            recipients: [
                'database' => [$event->user_id],
                'email' => is_null($email) ? [] : [$email],
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
