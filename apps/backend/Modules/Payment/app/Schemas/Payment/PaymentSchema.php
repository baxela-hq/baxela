<?php

namespace Modules\Payment\Schemas\Payment;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Payment\Schemas\Module;

class PaymentSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'payments';

    public const string ORDER_ID = 'order_id';

    public const string TRANSACTION_ID = 'transaction_id';

    public const string METHOD = 'method';

    public const string AMOUNT = 'amount';

    public const string STATUS = 'status';
}
