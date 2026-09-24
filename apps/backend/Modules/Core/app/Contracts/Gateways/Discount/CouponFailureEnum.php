<?php

namespace Modules\Core\Contracts\Gateways\Discount;

/**
 * Why an advisory evaluation failed. Callers translate these into their own
 * audience-facing dotted error codes (e.g. cart.coupon.*).
 */
enum CouponFailureEnum: string
{
    case NOT_FOUND = 'not_found';

    case INACTIVE = 'inactive';

    case NOT_STARTED = 'not_started';

    case EXPIRED = 'expired';

    case USAGE_LIMIT = 'usage_limit';

    case PER_USER_LIMIT = 'per_user_limit';

    case MIN_ORDER_AMOUNT = 'min_order_amount';
}
