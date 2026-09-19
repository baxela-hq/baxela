<?php

namespace Modules\Notification\Listeners\Payment\PaymentFailed;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Payment\PaymentFailedEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AdminRecipients;

class NotifyAdminPaymentFailedListener implements ShouldQueue
{
    use Queueable;

    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly OrderGatewayInterface $orderGateway,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PaymentFailedEvent $event): void
    {
        $order = $this->orderGateway->findOrder($event->order_id);

        if (is_null($order)) {
            return;
        }

        $message = new NotificationMessage(
            code: NotificationCodeEnum::PAYMENT_PAYMENT_FAILED->value,
            audience: 'admin',
            recipients: [
                'database' => AdminRecipients::databaseIds(),
            ],
            data: [
                'database' => [
                    'order_code' => $order->order_code,
                    'amount' => number_format((float) $event->amount, 2),
                ],
            ],
            meta: ['order_code' => $order->order_code],
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
