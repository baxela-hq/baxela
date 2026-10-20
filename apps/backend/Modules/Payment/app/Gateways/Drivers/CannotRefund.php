<?php

namespace Modules\Payment\Gateways\Drivers;

use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Payment\Exceptions\PaymentException;

/**
 * Gateways without a wired refund API: fail loudly instead of silently
 * skipping the gateway call and pretending money moved.
 */
trait CannotRefund
{
    public function refund(PaymentRefundInput $input): PaymentRefundResult
    {
        throw PaymentException::refundNotSupported();
    }
}
