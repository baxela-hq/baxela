<?php

namespace Modules\Order\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case UPDATE_INVALID_STATUS_TRANSITION = 'order.update.invalid_status_transition';

    case UPDATE_INVALID_PAYMENT_STATUS_TRANSITION = 'order.update.invalid_payment_status_transition';
}
