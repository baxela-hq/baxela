<?php

namespace Modules\Core\Contracts\Gateways\Payment;

/**
 * Marker for drivers whose refund() is wired: Stripe, PayPal move money
 * through the gateway API; manual records that an offline refund already
 * happened. Drivers without the marker throw
 * PaymentException::refundNotSupported() — admin surfaces query this
 * interface instead of discovering that after the click.
 */
interface RefundableDriver extends PaymentDriverInterface {}
