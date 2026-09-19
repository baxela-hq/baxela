<?php

namespace Modules\Notification\Actions\Admin\Notification;

use Modules\Core\Utils\Auth;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationSchema;

class MarkAllNotificationsReadAction
{
    /**
     * @return int remaining unread count for the caller (always 0)
     */
    public function handle(): int
    {
        Notification::query()
            ->where(NotificationSchema::USER_ID, Auth::id())
            ->where(NotificationSchema::AUDIENCE, NotificationAudienceEnum::ADMIN->value)
            ->whereNull(NotificationSchema::READ_AT)
            ->update([NotificationSchema::READ_AT => now()]);

        return Notification::query()
            ->where(NotificationSchema::USER_ID, Auth::id())
            ->where(NotificationSchema::AUDIENCE, NotificationAudienceEnum::ADMIN->value)
            ->whereNull(NotificationSchema::READ_AT)
            ->count();
    }
}
