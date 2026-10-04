<?php

namespace Modules\Notification\Listeners\Support\TicketCreated;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Support\TicketCreatedEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AdminRecipients;

class NotifyAdminSupportTicketCreatedListener implements ShouldQueue
{
    use Queueable;

    /**
     * Create the event listener.
     */
    public function __construct(private readonly NotificationDispatcherInterface $notificationDispatcher) {}

    /**
     * Handle the event.
     */
    public function handle(TicketCreatedEvent $event): void
    {
        $message = new NotificationMessage(
            code: NotificationCodeEnum::SUPPORT_TICKET_CREATED->value,
            audience: 'admin',
            recipients: [
                'email' => config('notification.notifications.admin_recipients.email', []),
                'database' => AdminRecipients::databaseIds(),
            ],
            data: [
                'database' => [
                    'ticket_id' => $event->id,
                    'subject' => $event->subject,
                ],
                'email' => [
                    'ticket_id' => $event->id,
                    'subject' => $event->subject,
                    'body' => $event->body,
                    'order_code' => $event->order_code,
                ],
            ],
            meta: ['ticket_id' => $event->id],
            locale: $event->locale,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
