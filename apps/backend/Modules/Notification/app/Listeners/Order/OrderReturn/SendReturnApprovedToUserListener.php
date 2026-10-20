<?php

namespace Modules\Notification\Listeners\Order\OrderReturn;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Order\OrderReturnApprovedEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class SendReturnApprovedToUserListener implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly AccessGatewayInterface $accessGateway,
    ) {}

    public function handle(OrderReturnApprovedEvent $event): void
    {
        $email = $this->accessGateway->getUserEmailsByIds([$event->user_id])[$event->user_id] ?? null;

        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_RETURN_APPROVED->value,
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
            locale: $event->locale ?? null,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
