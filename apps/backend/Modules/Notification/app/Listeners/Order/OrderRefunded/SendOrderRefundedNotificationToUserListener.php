<?php

namespace Modules\Notification\Listeners\Order\OrderRefunded;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Order\OrderRefundedEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

/**
 * Fires when the order's payment status reaches `refunded` — i.e. the
 * successful ledger refunds covered the full paid total (partial refunds
 * send nothing at order level; each refund fires PaymentRefundedEvent
 * instead, which is payment-module territory).
 */
class SendOrderRefundedNotificationToUserListener implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly AccessGatewayInterface $accessGateway,
    ) {}

    public function handle(OrderRefundedEvent $event): void
    {
        $email = $this->accessGateway->getUserEmailsByIds([$event->user_id])[$event->user_id] ?? null;
        $amount = number_format((float) $event->total_amount, 2);

        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_ORDER_REFUNDED->value,
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
            locale: $event->locale ?? null,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
