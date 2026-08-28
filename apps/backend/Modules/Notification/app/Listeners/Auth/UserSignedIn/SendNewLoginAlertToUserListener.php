<?php

namespace Modules\Notification\Listeners\Auth\UserSignedIn;

use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class SendNewLoginAlertToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly NotificationDispatcherInterface $notificationDispatcher) {}

    /**
     * Handle the event.
     */
    public function handle(UserSignedInEvent $event): void
    {
        $message = new NotificationMessage(
            code: NotificationCodeEnum::AUTH_USER_SIGNED_IN->value,
            audience: 'user',
            recipients: [
                'email' => [$event->email],
                'database' => [$event->id],
            ],
            data: [
                'email' => [
                    'site_name' => config('app.name'),
                    'signed_in_at' => $event->signed_in_at,
                ],
            ],
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
