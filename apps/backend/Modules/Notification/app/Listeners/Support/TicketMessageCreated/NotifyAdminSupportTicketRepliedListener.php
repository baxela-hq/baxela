<?php

namespace Modules\Notification\Listeners\Support\TicketMessageCreated;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Support\TicketMessageCreatedEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AdminRecipients;

class NotifyAdminSupportTicketRepliedListener implements ShouldQueue
{
    use Queueable;

    /**
     * Create the event listener.
     */
    public function __construct(private readonly NotificationDispatcherInterface $notificationDispatcher) {}

    /**
     * Handle the event. Only customer replies reach the admins — staff
     * replies are covered by the customer-facing answered notification.
     */
    public function handle(TicketMessageCreatedEvent $event): void
    {
        if ($event->sender !== 'customer') {
            return;
        }

        $message = new NotificationMessage(
            code: NotificationCodeEnum::SUPPORT_TICKET_REPLIED->value,
            audience: 'admin',
            recipients: [
                'email' => config('notification.notifications.admin_recipients.email', []),
                'database' => AdminRecipients::databaseIds(),
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
                    'order_code' => $event->order_code,
                ],
            ],
            meta: ['ticket_id' => $event->ticket_id],
            locale: $event->locale,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
