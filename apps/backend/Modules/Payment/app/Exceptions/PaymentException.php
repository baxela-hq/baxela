<?php

namespace Modules\Payment\Exceptions;

use Modules\Core\Exceptions\BaseException;

class PaymentException extends BaseException
{
    public static function processInvalidOrder(): PaymentException
    {
        return new self(ErrorCodeEnum::PROCESS_INVALID_ORDER);
    }
}
