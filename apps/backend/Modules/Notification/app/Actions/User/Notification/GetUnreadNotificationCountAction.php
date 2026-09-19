<?php

namespace Modules\Notification\Actions\User\Notification;

use Modules\Core\Utils\Auth;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationSchema;

class GetUnreadNotificationCountAction
{
    public function handle(): int
    {
        return Notification::query()
            ->where(NotificationSchema::USER_ID, Auth::id())
            ->whereNull(NotificationSchema::READ_AT)
            ->count();
    }
}
