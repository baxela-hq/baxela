<?php

namespace Modules\Notification\Listeners\Order\OrderCreated;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AdminRecipients;
use Modules\Notification\Support\AmountFormatter;

class NotifyAdminNewOrderListener implements ShouldQueue
{
    use Queueable;

    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly AmountFormatter $amountFormatter,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrderCreatedEvent $event): void
    {
        $amount = $this->amountFormatter->forCharge(
            $event->total_amount,
            $this->amountFormatter->baseForOrder($event->currency_id),
        );

        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
            audience: 'admin',
            recipients: [
                'email' => config('notification.notifications.admin_recipients.email', []),
                'database' => AdminRecipients::databaseIds(),
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
            meta: ['order_code' => $event->order_code, 'order_id' => $event->id],
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
