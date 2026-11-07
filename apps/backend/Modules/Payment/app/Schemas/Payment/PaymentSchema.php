<?php

namespace Modules\Payment\Schemas\Payment;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Payment\Schemas\Module;

class PaymentSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'payments';

    public const string ORDER_ID = 'order_id';

    /**
     * Request key carrying the customer's public order code; ORDER_ID stays
     * the internal int FK once the order is resolved.
     */
    public const string REQ_ORDER_CODE = 'order_code';

    public const string TRANSACTION_ID = 'transaction_id';

    /**
     * The gateway-side payment identifier (Adyen pspReference, Checkout/MP
     * payment id, Razorpay pay_ id), persisted by the webhook settle path
     * — refund APIs key on it, unlike TRANSACTION_ID which stays the
     * internal webhook match key. Payments settled before the column
     * shipped carry NULL and refuse gateway refunds explicitly.
     */
    public const string GATEWAY_TRANSACTION_ID = 'gateway_transaction_id';

    public const string METHOD = 'method';

    public const string AMOUNT = 'amount';

    /**
     * Currency of the amount, snapshotted from the order at creation.
     */
    public const string CURRENCY_ID = 'currency_id';

    public const string STATUS = 'status';

    /**
     * Computed wire field (no backing column): whether the payment's
     * driver can execute refunds through the admin refund flow.
     */
    public const string IS_REFUNDABLE = 'is_refundable';
}
