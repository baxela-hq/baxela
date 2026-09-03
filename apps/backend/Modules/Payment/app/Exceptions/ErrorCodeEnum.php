<?php

namespace Modules\Payment\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case PROCESS_INVALID_ORDER = 'payment.process.invalid_order';

    case PROCESS_METHOD_NOT_SUPPORTED = 'payment.process.method_not_supported';

    case UPDATE_INVALID_STATUS_TRANSITION = 'payment.update.invalid_status_transition';

    case WEBHOOK_NOT_SUPPORTED = 'payment.webhook.not_supported';

    case WEBHOOK_INVALID = 'payment.webhook.invalid';
}
