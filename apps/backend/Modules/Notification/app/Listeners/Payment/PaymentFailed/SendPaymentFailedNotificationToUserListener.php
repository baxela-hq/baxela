<?php

namespace Modules\Notification\Listeners\Payment\PaymentFailed;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Payment\PaymentFailedEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AmountFormatter;

class SendPaymentFailedNotificationToUserListener implements ShouldQueue
{
    use Queueable;

    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly OrderGatewayInterface $orderGateway,
        private readonly AmountFormatter $amountFormatter,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PaymentFailedEvent $event): void
    {
        $order = $this->orderGateway->findOrder($event->order_id);

        if (is_null($order) || is_null($order->user_id)) {
            return;
        }

        $message = new NotificationMessage(
            code: NotificationCodeEnum::PAYMENT_PAYMENT_FAILED->value,
            audience: 'user',
            recipients: [
                'database' => [$order->user_id],
            ],
            data: [
                'database' => [
                    'order_code' => $order->order_code,
                    'amount' => $this->amountFormatter->forCharge((float) $event->amount, $order->currency_code),
                ],
            ],
            meta: ['order_code' => $order->order_code],
            locale: $event->locale,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
