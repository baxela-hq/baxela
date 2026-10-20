<?php

namespace Modules\Order\Schemas\OrderReturn;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Order\Schemas\Module;

class OrderReturnSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'returns';

    public const string ORDER_ID = 'order_id';

    public const string USER_ID = 'user_id';

    public const string STATUS = 'status';

    public const string REASON = 'reason';

    public const string NOTE = 'note';

    public const string ADMIN_NOTE = 'admin_note';

    public const string TRACKING_NUMBER = 'tracking_number';

    /**
     * Idempotency anchor for stock restoration: set exactly once, in the
     * same transaction as the transition into `received`, so retries and
     * concurrent admins can never restore the same items twice.
     */
    public const string STOCK_RESTORED_AT = 'stock_restored_at';

    /** Virtual request key for the received-transition's stock choice. */
    public const string REQ_RESTORE_STOCK = 'restore_stock';

    public const string RES_ORDER = 'order';
}
