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

    public const string METHOD = 'method';

    public const string AMOUNT = 'amount';

    public const string STATUS = 'status';
}
