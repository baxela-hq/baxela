<?php

namespace Modules\Core\Contracts\Events\Payment;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class PaymentRefundedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $payment_id;

    public int $order_id;

    public float $amount;

    /** Whether this refund completed the order's full refund. */
    public bool $full;

    public string $transaction_id;

    public string $status;

    public string $locale;
}
