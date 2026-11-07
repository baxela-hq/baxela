<?php

namespace Modules\Core\Contracts\Gateways\Payment\DTOs;

class WebhookResult
{
    public function __construct(
        public readonly string $transaction_id,
        public readonly string $status,
        /**
         * The gateway-side payment identifier (Adyen pspReference, Checkout
         * or MP payment id, Razorpay pay_ id), when the event carries it.
         * Persisted on the payment at settle time — refund APIs key on it.
         */
        public readonly ?string $gateway_transaction_id = null,
        /**
         * A refund-lifecycle outcome (refund accepted/failed/reversed at
         * the gateway). When present, the settle path routes to the refund
         * ledger instead of the payment row — the two branches never touch
         * each other's rows.
         */
        public readonly ?RefundWebhookOutcome $refund_outcome = null,
    ) {}
}
