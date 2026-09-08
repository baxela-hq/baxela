<?php

namespace Modules\Notification\Listeners\Contact\ContactMessageCreated;

use Modules\Core\Contracts\Events\Contact\ContactMessageCreatedEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class NotifyAdminContactMessageCreatedListener
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly NotificationDispatcherInterface $notificationDispatcher) {}

    /**
     * Handle the event.
     */
    public function handle(ContactMessageCreatedEvent $event): void
    {
        $message = new NotificationMessage(
            code: NotificationCodeEnum::CONTACT_MESSAGE_CREATED->value,
            audience: 'admin',
            recipients: [
                'email' => config('notification.notifications.admin_recipients.email', []),
            ],
            data: [
                'email' => [
                    'name' => $event->name,
                    'email' => $event->email,
                    'phone' => $event->phone,
                    'subject' => $event->subject,
                    'content' => $event->content,
                ],
            ],
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
