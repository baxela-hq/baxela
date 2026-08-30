<?php

namespace Modules\Order\Schemas\OrderAddress;

use Modules\Core\Schemas\Shared\PkAndCreatedAtTrait;
use Modules\Order\Schemas\Module;

class OrderAddressSchema
{
    use PkAndCreatedAtTrait;

    public const string TABLE = Module::DB_PREFIX.'order_addresses';

    public const string ORDER_ID = 'order_id';

    public const string TYPE = 'type';

    public const string FULL_NAME = 'full_name';

    public const string PHONE = 'phone';

    public const string ADDRESS_LINE = 'address_line';

    public const string CITY = 'city';

    public const string POSTAL_CODE = 'postal_code';

    public const string COUNTRY_CODE = 'country_code';
}
