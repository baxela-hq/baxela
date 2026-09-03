<?php

namespace Modules\Core\Contracts\Gateways\Payment\DTOs;

class PaymentGatewayResult
{
    public function __construct(
        public readonly ?string $redirect_url = null,
        public readonly ?string $transaction_id = null,
    ) {}
}
