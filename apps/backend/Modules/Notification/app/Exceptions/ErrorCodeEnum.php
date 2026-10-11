<?php

namespace Modules\Notification\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case PREFERENCE_LOCKED = 'notification.preference.locked';
}
