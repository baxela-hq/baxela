<?php

namespace Modules\Core\Contracts\Gateways\Payment\DTOs;

class PaymentRefundResult
{
    public function __construct(
        /** The gateway's refund id, persisted on the ledger row. */
        public readonly ?string $transaction_id = null,
    ) {}
}
