<?php

namespace Modules\Notification\Services\Notification\Enums;

enum ChannelEnum: string
{
    case EMAIL = 'email';
    case DATABASE = 'database';
}
