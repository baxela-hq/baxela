<?php

namespace Modules\Payment\Exceptions;

use Modules\Core\Exceptions\BaseException;

class PaymentException extends BaseException
{
    public static function processInvalidOrder(): PaymentException
    {
        return new self(ErrorCodeEnum::PROCESS_INVALID_ORDER->value);
    }

    public static function methodNotSupported(): PaymentException
    {
        return new self(ErrorCodeEnum::PROCESS_METHOD_NOT_SUPPORTED->value);
    }

    public static function invalidStatusTransition(): PaymentException
    {
        return new self(ErrorCodeEnum::UPDATE_INVALID_STATUS_TRANSITION->value);
    }

    public static function webhookNotSupported(): PaymentException
    {
        return new self(ErrorCodeEnum::WEBHOOK_NOT_SUPPORTED->value);
    }

    public static function webhookInvalid(): PaymentException
    {
        return new self(ErrorCodeEnum::WEBHOOK_INVALID->value);
    }
}
