<?php

namespace Modules\Shipping\Schemas\Shipment;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Shipping\Schemas\Module;

class ShipmentSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'shipments';

    public const string ORDER_ID = 'order_id';

    public const string CARRIER_NAME = 'carrier_name';

    public const string TRACKING_NUMBER = 'tracking_number';

    public const string TRACKING_URL = 'tracking_url';

    public const string STATUS = 'status';

    public const string SHIPPED_AT = 'shipped_at';

    public const string DELIVERED_AT = 'delivered_at';

    public const string NOTES = 'notes';
}
