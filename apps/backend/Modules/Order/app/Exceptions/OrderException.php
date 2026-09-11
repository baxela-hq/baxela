<?php

namespace Modules\Order\Exceptions;

use Modules\Core\Exceptions\BaseException;

class OrderException extends BaseException
{
    public static function invalidStatusTransition(): OrderException
    {
        return new self(ErrorCodeEnum::UPDATE_INVALID_STATUS_TRANSITION->value);
    }

    public static function invalidPaymentStatusTransition(): OrderException
    {
        return new self(ErrorCodeEnum::UPDATE_INVALID_PAYMENT_STATUS_TRANSITION->value);
    }
}
