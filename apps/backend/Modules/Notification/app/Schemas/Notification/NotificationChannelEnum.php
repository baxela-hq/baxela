<?php

namespace Modules\Notification\Schemas\Notification;

enum NotificationChannelEnum: string
{
    case DATABASE = 'database';

    case EMAIL = 'email';

    case WEBPUSH = 'webpush';
}
