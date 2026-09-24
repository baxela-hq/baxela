<?php

namespace Modules\Discount\Schemas\Coupon;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Discount\Schemas\Module;

class CouponSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'coupons';

    /**
     * Case-insensitive customer-facing code, stored uppercase-trimmed.
     */
    public const string CODE = 'code';

    /**
     * Internal admin-facing label; never shown to customers.
     */
    public const string NAME = 'name';

    public const string TYPE = 'type';

    /**
     * VALUE semantics (major units, decimal(12,2)):
     *   percent → 15.00 == 15% (NOT a 0.15 fraction), allowed 0.01–100
     *   fixed   → a major-unit amount off the merchandise subtotal
     */
    public const string VALUE = 'value';

    /**
     * Optional ceiling for percent coupons only (major units).
     */
    public const string MAX_DISCOUNT_AMOUNT = 'max_discount_amount';

    /**
     * Optional merchandise-subtotal floor (major units, pre-discount,
     * excluding shipping/tax) required to use the coupon.
     */
    public const string MIN_ORDER_AMOUNT = 'min_order_amount';

    /**
     * Validity window in UTC (inclusive bounds; null = unbounded).
     */
    public const string STARTS_AT = 'starts_at';

    public const string ENDS_AT = 'ends_at';

    public const string USAGE_LIMIT = 'usage_limit';

    public const string PER_USER_LIMIT = 'per_user_limit';

    /**
     * Denormalized counter of retained redemptions. Invariant:
     * usage_count == COUNT(discount_redemptions) for the coupon, and every
     * mutation of either happens under the coupon row lock inside
     * DiscountGateway::recordRedemption()/releaseRedemption().
     */
    public const string USAGE_COUNT = 'usage_count';

    public const string IS_ACTIVE = 'is_active';
}
