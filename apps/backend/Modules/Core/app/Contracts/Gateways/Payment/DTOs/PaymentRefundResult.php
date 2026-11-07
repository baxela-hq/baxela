<?php

namespace Modules\Core\Contracts\Gateways\Payment\DTOs;

class PaymentRefundResult
{
    public function __construct(
        /** The gateway's refund id, persisted on the ledger row. */
        public readonly ?string $transaction_id = null,
        /**
         * The gateway accepted the refund but its definitive outcome only
         * arrives via webhook (Adyen, Checkout.com, Razorpay): the ledger
         * row moves to processing and stays reserved until the webhook
         * confirms — a 2xx accept is never treated as completed money
         * movement.
         */
        public readonly bool $accepted_pending = false,
    ) {}
}
