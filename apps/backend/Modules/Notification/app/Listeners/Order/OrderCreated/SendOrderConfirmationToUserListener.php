<?php

namespace Modules\Notification\Listeners\Order\OrderCreated;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class SendOrderConfirmationToUserListener implements ShouldQueue
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
    public function handle(OrderCreatedEvent $event): void
    {
        $email = $this->accessGateway->getUserEmailsByIds([$event->user_id])[$event->user_id] ?? null;
        $amount = number_format((float) $event->total_amount, 2);

        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
            audience: 'user',
            recipients: [
                'database' => [$event->user_id],
                'email' => is_null($email) ? [] : [$email],
            ],
            data: [
                'database' => [
                    'order_code' => $event->order_code,
                    'amount' => $amount,
                ],
                'email' => [
                    'order_code' => $event->order_code,
                    'amount' => $amount,
                ],
            ],
            meta: ['order_code' => $event->order_code],
            locale: $event->locale,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
