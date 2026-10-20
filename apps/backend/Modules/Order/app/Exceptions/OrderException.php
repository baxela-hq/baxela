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

    public static function returnNotFound(): OrderException
    {
        return new self(ErrorCodeEnum::RETURN_NOT_FOUND->value, 404);
    }

    /**
     * The order does not meet the return eligibility rules (payment state,
     * fulfillment state, window elapsed, or another return already open).
     */
    public static function returnIneligible(): OrderException
    {
        return new self(ErrorCodeEnum::RETURN_INELIGIBLE->value);
    }

    public static function returnInvalidTransition(): OrderException
    {
        return new self(ErrorCodeEnum::RETURN_INVALID_TRANSITION->value);
    }
}
