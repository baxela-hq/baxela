<?php

namespace Modules\Order\Schemas\Order;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Order\Schemas\Module;

class OrderSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'orders';

    public const string USER_ID = 'user_id';

    public const string STATUS = 'status';

    public const string TOTAL_AMOUNT = 'total_amount';

    public const string EXPIRES_AT = 'expires_at';

    public const string RES_ITEMS = 'items';

    public const string RES_ADDRESSES = 'addresses';
}
