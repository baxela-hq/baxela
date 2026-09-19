<?php

namespace Modules\Notification\Listeners\Order\OrderCompleted;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Order\OrderCompletedEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class SendOrderCompletedNotificationToUserListener implements ShouldQueue
{
    use Queueable;

    /**
     * Create the event listener.
     */
    public function __construct(private readonly NotificationDispatcherInterface $notificationDispatcher) {}

    /**
     * Handle the event.
     */
    public function handle(OrderCompletedEvent $event): void
    {
        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_ORDER_COMPLETED->value,
            audience: 'user',
            recipients: [
                'database' => [$event->user_id],
            ],
            data: [
                'database' => [
                    'order_code' => $event->order_code,
                ],
            ],
            meta: ['order_code' => $event->order_code],
            locale: $event->locale,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
