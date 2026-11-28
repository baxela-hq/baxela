<?php

namespace Modules\Core\Contracts\Gateways\Payment\DTOs;

class PaymentInitiateInput
{
    public function __construct(
        public readonly int $payment_id,
        public readonly int $order_id,
        public readonly float $amount,
        public readonly string $method,
        public readonly ?string $order_code = null,
        public readonly ?string $currency = null,
        public readonly ?int $currency_decimal_places = null,
        /** Stable tenant + principal + key hash (§6.6), never a claim token. */
        public readonly ?string $idempotency_key = null,
    ) {}
}
