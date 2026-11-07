<?php

namespace Modules\Core\Contracts\Events\Payment;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

/**
 * The gateway reversed a refund it had previously completed (Adyen
 * REFUNDED_REVERSED, a late REFUND_FAILED on a finalized refund): the
 * ledger row is terminal `reversed` and the order accounting has been
 * restored from the success-only ledger sums.
 */
class PaymentRefundReversedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $payment_id;

    public int $order_id;

    public float $amount;

    public string $transaction_id;

    /** Whether order accounting had been applied and was restored (false: the reversal arrived before the refund was ever finalized). */
    public bool $restored_accounting;

    public string $locale;
}
