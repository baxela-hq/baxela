<?php

namespace Modules\Cart\Schemas\Cart;

use Modules\Cart\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class CartSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'carts';

    public const string USER_ID = 'user_id';

    public const string TOKEN = 'token';

    /**
     * Code of the coupon applied to this cart (uppercase); at most one at
     * a time. Stored as a plain string, never an FK to the Discount module.
     */
    public const string COUPON_CODE = 'coupon_code';
}
