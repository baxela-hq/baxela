<?php

namespace Modules\Core\Contracts\Gateways\Payment\DTOs;

/**
 * A refund-lifecycle outcome announced by a gateway webhook. The echoed
 * merchant reference (Baxela's ledger uuid) and the gateway's own refund
 * id are deliberately separate properties — they are different
 * identifiers and must never overload each other (the echoed uuid is the
 * correlation key; the gateway refund id is the documented fallback and
 * reconciliation metadata).
 */
class RefundWebhookOutcome
{
    public function __construct(
        /**
         * Baxela's refund ledger uuid, echoed by the gateway. Gateways
         * without a reference echo (Mercado Pago notifications) leave it
         * null — correlation then goes through the gateway refund id.
         */
        public readonly ?string $reference,
        /** One of: success, failed, reversed. */
        public readonly string $status,
        /** The gateway's refund id, when the event carries one. */
        public readonly ?string $gateway_refund_id = null,
        /** The gateway-side payment id, when the event carries one — validated against the payment. */
        public readonly ?string $gateway_payment_id = null,
        /** Minor units, when the event carries the amount — validated against the ledger row. */
        public readonly ?int $amount_minor = null,
        public readonly ?string $currency = null,
    ) {}
}
