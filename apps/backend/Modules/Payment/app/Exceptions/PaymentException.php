<?php

namespace Modules\Payment\Exceptions;

use Modules\Core\Exceptions\BaseException;
use RuntimeException;

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

    public static function methodInactive(): PaymentException
    {
        return new self(ErrorCodeEnum::PROCESS_METHOD_INACTIVE->value);
    }

    public static function gatewayUnconfigured(): PaymentException
    {
        // A gateway without credentials is a server-side configuration
        // problem, not a client error — the 400 envelope this returns is
        // exempt from reporting, so surface the misconfiguration here; the
        // trace names the gateway client that hit it.
        report(new RuntimeException('payment gateway is not configured'));

        return new self(ErrorCodeEnum::PROCESS_GATEWAY_UNCONFIGURED->value);
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
