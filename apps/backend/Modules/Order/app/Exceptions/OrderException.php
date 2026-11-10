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

    public static function invoiceNotFound(): OrderException
    {
        return new self(ErrorCodeEnum::INVOICE_NOT_FOUND->value, 404);
    }

    /**
     * The order does not qualify for invoice issuance: its payment status
     * is not one of the paid states (paid, partially refunded, refunded).
     */
    public static function invoiceIneligible(): OrderException
    {
        return new self(ErrorCodeEnum::INVOICE_INELIGIBLE->value);
    }

    public static function invoiceInvalidTransition(): OrderException
    {
        return new self(ErrorCodeEnum::INVOICE_INVALID_TRANSITION->value);
    }

    /**
     * The PDF artifact has not finished generating (D7): downloads serve
     * ready documents only — never an incomplete file.
     */
    public static function invoicePdfNotReady(): OrderException
    {
        return new self(ErrorCodeEnum::INVOICE_PDF_NOT_READY->value, 409);
    }

    /**
     * Only a failed generation can be retried through the admin surface
     * (D7 v2.2); regeneration of a ready artifact is ops-only artisan.
     */
    public static function invoicePdfNotRetryable(): OrderException
    {
        return new self(ErrorCodeEnum::INVOICE_PDF_NOT_RETRYABLE->value, 409);
    }
}
