<?php

namespace Modules\Notification\Listeners\Order\OrderReturn;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Order\OrderReturnRequestedEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AdminRecipients;

class NotifyAdminReturnRequestedListener implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    public function handle(OrderReturnRequestedEvent $event): void
    {
        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_RETURN_REQUESTED->value,
            audience: 'admin',
            recipients: [
                'email' => config('notification.notifications.admin_recipients.email', []),
                'database' => AdminRecipients::databaseIds(),
            ],
            data: [
                'database' => [
                    'order_code' => $event->order_code,
                    'reason' => $event->reason,
                ],
                'email' => [
                    'order_code' => $event->order_code,
                    'reason' => $event->reason,
                ],
            ],
            meta: ['order_code' => $event->order_code],
            locale: $event->locale ?? null,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
