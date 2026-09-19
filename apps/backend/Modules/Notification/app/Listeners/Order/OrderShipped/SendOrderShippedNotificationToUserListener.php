<?php

namespace Modules\Notification\Listeners\Order\OrderShipped;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Order\OrderShippedEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class SendOrderShippedNotificationToUserListener implements ShouldQueue
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
    public function handle(OrderShippedEvent $event): void
    {
        $email = $this->accessGateway->getUserEmailsByIds([$event->user_id])[$event->user_id] ?? null;

        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_ORDER_SHIPPED->value,
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
                ],
            ],
            meta: ['order_code' => $event->order_code],
            locale: $event->locale,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
