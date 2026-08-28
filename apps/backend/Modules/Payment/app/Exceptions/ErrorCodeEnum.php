<?php

namespace Modules\Payment\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case PROCESS_INVALID_ORDER = 'payment.process.invalid_order';
}
