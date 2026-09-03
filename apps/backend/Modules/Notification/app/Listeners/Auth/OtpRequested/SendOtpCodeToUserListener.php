<?php

namespace Modules\Notification\Listeners\Auth\OtpRequested;

use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class SendOtpCodeToUserListener
{
    protected const int OTP_TTL_MINUTES = 5;

    /**
     * Create the event listener.
     */
    public function __construct(private readonly NotificationDispatcherInterface $notificationDispatcher) {}

    /**
     * Handle the event.
     */
    public function handle(OtpRequestedEvent $event): void
    {
        $message = new NotificationMessage(
            code: NotificationCodeEnum::AUTH_USER_OTP_CODE->value,
            audience: 'user',
            recipients: [
                'email' => [$event->email],
            ],
            data: [
                'email' => [
                    'code' => $event->code,
                    'action' => $event->action,
                    'expires_in' => self::OTP_TTL_MINUTES,
                ],
            ],
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
