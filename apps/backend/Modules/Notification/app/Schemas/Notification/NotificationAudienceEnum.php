<?php

namespace Modules\Notification\Schemas\Notification;

enum NotificationAudienceEnum: string
{
    case ADMIN = 'admin';
    case USER = 'user';
}
