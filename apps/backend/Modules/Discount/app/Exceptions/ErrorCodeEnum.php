<?php

namespace Modules\Discount\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case COUPON_NOT_FOUND = 'discount.coupon.not_found';

    case COUPON_CREATION_FAILED = 'discount.coupon.creation_failed';

    case COUPON_UPDATE_FAILED = 'discount.coupon.update_failed';

    case COUPON_HAS_REDEMPTIONS = 'discount.coupon.has_redemptions';
}
