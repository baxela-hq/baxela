<?php

namespace Modules\Notification\Listeners\Payment\PaymentSucceeded;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Payment\PaymentSucceededEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AdminRecipients;
use Modules\Notification\Support\AmountFormatter;

/**
 * Staff-side payment alert. Order-level notifications already reach the
 * customer (see the Order listeners), so payment success fans out to
 * admins only — avoiding a double alert per checkout.
 */
class NotifyAdminPaymentSucceededListener implements ShouldQueue
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
    public function handle(PaymentSucceededEvent $event): void
    {
        $order = $this->orderGateway->findOrder($event->order_id);

        if (is_null($order)) {
            return;
        }

        $message = new NotificationMessage(
            code: NotificationCodeEnum::PAYMENT_PAYMENT_SUCCEEDED->value,
            audience: 'admin',
            recipients: [
                'database' => AdminRecipients::databaseIds(),
            ],
            data: [
                'database' => [
                    'order_code' => $order->order_code,
                    'amount' => $this->amountFormatter->forCharge((float) $event->amount, $order->currency_code),
                ],
            ],
            meta: ['order_code' => $order->order_code, 'order_id' => (int) $order->id],
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
