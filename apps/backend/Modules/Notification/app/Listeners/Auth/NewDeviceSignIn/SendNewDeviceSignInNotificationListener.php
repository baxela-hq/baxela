<?php

namespace Modules\Notification\Listeners\Auth\NewDeviceSignIn;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Contracts\Events\Auth\NewDeviceSignInEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

/**
 * S.4: dispatches the new-device sign-in alert (database + email). The
 * code is locked (LockedNotificationCodes), so user preferences can never
 * mute it. Best-effort delivery: queued after the authentication
 * transaction commits, so delivery failures never affect sign-in (D8).
 */
class SendNewDeviceSignInNotificationListener implements ShouldQueue
{
    use Queueable;

    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(NewDeviceSignInEvent $event): void
    {
        $data = [
            'device_name' => $event->device_name,
            'signed_in_at' => $event->signed_in_at,
        ];

        $message = new NotificationMessage(
            code: NotificationCodeEnum::AUTH_USER_NEW_DEVICE->value,
            audience: 'user',
            recipients: [
                'database' => [$event->id],
                'email' => [$event->email],
            ],
            data: [
                'database' => $data,
                'email' => $data,
            ],
            locale: $event->locale,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
