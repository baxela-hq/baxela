<?php

namespace Modules\Notification\Listeners\Order\OrderCancelled;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class SendOrderCancelledNotificationToUserListener implements ShouldQueue
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
    public function handle(OrderCancelledEvent $event): void
    {
        $email = $this->accessGateway->getUserEmailsByIds([$event->user_id])[$event->user_id] ?? null;

        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_ORDER_CANCELLED->value,
            audience: 'user',
            recipients: [
                'database' => [$event->user_id],
                'email' => is_null($email) ? [] : [$email],
            ],
            data: [
                'database' => [
                    'order_code' => $event->order_code,
                ],
                'email' => [
                    'order_code' => $event->order_code,
                    'reason' => $event->reason,
                ],
            ],
            meta: [
                'order_code' => $event->order_code,
                'reason' => $event->reason,
            ],
            locale: $event->locale,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
