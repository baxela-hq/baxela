<?php

namespace Modules\Notification\Actions\User\Notification;

use Modules\Core\Utils\Auth;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationSchema;

class MarkNotificationReadAction
{
    /**
     * The ownership scope makes a foreign id 404 exactly like an
     * unknown one, so the endpoint never leaks other users' rows.
     */
    public function handle(string $id): Notification
    {
        $notification = Notification::query()
            ->where(NotificationSchema::ID, $id)
            ->where(NotificationSchema::USER_ID, Auth::id())
            ->firstOrFail();

        if (is_null($notification->{NotificationSchema::READ_AT})) {
            $notification->{NotificationSchema::READ_AT} = now();
            $notification->save();
        }

        return $notification;
    }
}
