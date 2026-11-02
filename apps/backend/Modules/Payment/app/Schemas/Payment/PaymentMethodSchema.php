<?php

namespace Modules\Payment\Schemas\Payment;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Payment\Schemas\Module;

class PaymentMethodSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'payment_methods';

    public const string METHOD = 'method';

    public const string IS_ACTIVE = 'is_active';

    public const string SORT_ORDER = 'sort_order';

    /**
     * Computed wire fields (no backing column): whether the driver is
     * registered in config, carries its credentials, and can execute
     * refunds through the admin refund flow.
     */
    public const string IS_CONFIGURED = 'is_configured';

    public const string IS_REGISTERED = 'is_registered';

    public const string IS_REFUNDABLE = 'is_refundable';
}
