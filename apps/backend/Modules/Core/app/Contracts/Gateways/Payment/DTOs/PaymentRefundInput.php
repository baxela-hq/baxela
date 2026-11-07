<?php

namespace Modules\Core\Contracts\Gateways\Payment\DTOs;

class PaymentRefundInput
{
    public function __construct(
        public readonly int $payment_id,
        public readonly int $order_id,
        /** The gateway transaction id stored at initiation (session/order id). */
        public readonly string $transaction_id,
        public readonly float $amount,
        public readonly ?string $currency = null,
        public readonly ?int $currency_decimal_places = null,
        /**
         * Stable per-refund key (the ledger row's uuid) — gateways that
         * support idempotency headers must receive it so a retried call
         * can never produce a second gateway refund.
         */
        public readonly ?string $idempotency_key = null,
        /**
         * The gateway-side payment identifier persisted at webhook settle
         * time (Adyen pspReference, Checkout/MP payment id, Razorpay
         * pay_ id) — what the refund APIs themselves key on. Drivers
         * without it (payments settled before the column, or a gateway
         * that never announced it) must refuse explicitly instead of
         * guessing what to refund.
         */
        public readonly ?string $gateway_transaction_id = null,
    ) {}
}
