<?php

namespace Modules\Core\Contracts\Events\Payment;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

/**
 * Fired once per SUCCESSFUL refund finalization, after the finalize
 * transaction commits (gap-B1 D10/D15: at-least-once intent; the crash
 * window between commit and dispatch is closed by reconciliation, not
 * an outbox). Distinct from OrderRefundedEvent, which is an
 * order-status transition that only fires on the full-refund crossing.
 *
 * The refund-backed credit-note listener keys its idempotency on
 * refund_uuid. Note (execution deviation from the plan's payload
 * sketch): order_code is resolved by the listener from order_id — one
 * indexed lookup — instead of being fetched cross-module at every
 * dispatch site.
 */
class RefundFinalizedEvent extends AbstractBaseEvent
{
    public int $payment_id;

    public int $order_id;

    public string $refund_uuid;

    /** The finalized amount in integer minor units (D11). */
    public int $amount_minor;

    public string $currency_code;

    public ?string $reason = null;

    public ?string $locale = null;
}
