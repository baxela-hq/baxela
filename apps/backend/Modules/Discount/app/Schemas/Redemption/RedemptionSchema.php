<?php

namespace Modules\Discount\Schemas\Redemption;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Discount\Schemas\Module;

class RedemptionSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'redemptions';

    public const string COUPON_ID = 'coupon_id';

    /**
     * Order owning the redemption. Deliberately NOT a cross-module foreign
     * key (same boundary as cart_items.variant_id): the Discount module
     * knows orders only by id through the gateway contract.
     */
    public const string ORDER_ID = 'order_id';

    public const string USER_ID = 'user_id';

    /**
     * Major-unit snapshot of the discount actually consumed.
     */
    public const string DISCOUNT_AMOUNT = 'discount_amount';

    public const string RES_COUPON = 'coupon';
}
