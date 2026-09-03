<?php

namespace Modules\Core\Contracts\Gateways\Payment\DTOs;

class PaymentInitiateInput
{
    public function __construct(
        public readonly int $payment_id,
        public readonly int $order_id,
        public readonly float $amount,
        public readonly string $method,
    ) {}
}
