<?php

namespace Modules\Notification\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case PREFERENCE_LOCKED = 'notification.preference.locked';

    case TELEGRAM_UNCONFIGURED = 'notification.telegram.unconfigured';

    case TELEGRAM_LINK_INVALID = 'notification.telegram.link_invalid';

    case TELEGRAM_CHAT_UNREACHABLE = 'notification.telegram.chat_unreachable';

    case TELEGRAM_NOT_LINKED = 'notification.telegram.not_linked';

    case TELEGRAM_WEBHOOK_UNAUTHORIZED = 'notification.telegram.webhook_unauthorized';

    case TELEGRAM_REQUEST_FAILED = 'notification.telegram.request_failed';
}
