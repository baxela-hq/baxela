<?php

namespace Modules\Payment\Schemas\PaymentRefund;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Payment\Schemas\Module;

class PaymentRefundSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'refunds';

    /**
     * Stable per-refund identity doubling as the gateway idempotency key —
     * a retried gateway call can never create a second refund.
     */
    public const string UUID = 'uuid';

    public const string PAYMENT_ID = 'payment_id';

    public const string ORDER_ID = 'order_id';

    public const string AMOUNT = 'amount';

    public const string REASON = 'reason';

    public const string STATUS = 'status';

    /** The gateway's refund id, once known. */
    public const string TRANSACTION_ID = 'transaction_id';

    /** Admin who executed the refund. */
    public const string REFUNDED_BY = 'refunded_by';

    public const string RES_PAYMENT = 'payment';
}
