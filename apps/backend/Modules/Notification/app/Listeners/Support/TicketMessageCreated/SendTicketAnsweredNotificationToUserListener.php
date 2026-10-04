<?php

namespace Modules\Notification\Listeners\Support\TicketMessageCreated;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Support\TicketMessageCreatedEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class SendTicketAnsweredNotificationToUserListener implements ShouldQueue
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
     * Handle the event. Only staff replies notify the ticket owner.
     */
    public function handle(TicketMessageCreatedEvent $event): void
    {
        if ($event->sender !== 'admin') {
            return;
        }

        $email = $this->accessGateway->getUserEmailsByIds([$event->user_id])[$event->user_id] ?? null;

        $message = new NotificationMessage(
            code: NotificationCodeEnum::SUPPORT_TICKET_ANSWERED->value,
            audience: 'user',
            recipients: [
                'database' => [$event->user_id],
                'email' => is_null($email) ? [] : [$email],
            ],
            data: [
                'database' => [
                    'ticket_id' => $event->ticket_id,
                    'subject' => $event->subject,
                ],
                'email' => [
                    'ticket_id' => $event->ticket_id,
                    'subject' => $event->subject,
                    'body' => $event->body,
                ],
            ],
            meta: ['ticket_id' => $event->ticket_id],
            locale: $event->locale,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
