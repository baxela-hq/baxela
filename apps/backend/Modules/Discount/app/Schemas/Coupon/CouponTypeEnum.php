<?php

namespace Modules\Discount\Schemas\Coupon;

enum CouponTypeEnum: string
{
    case PERCENT = 'percent';

    case FIXED = 'fixed';
}
