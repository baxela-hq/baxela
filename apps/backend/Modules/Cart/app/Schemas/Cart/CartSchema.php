<?php

namespace Modules\Cart\Schemas\Cart;

use Modules\Cart\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class CartSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'carts';

    public const string USER_ID = 'user_id';
}
