<?php

namespace Modules\Cart\Exceptions\User\Coupon;

use Modules\Cart\Exceptions\ErrorCodeEnum;
use Modules\Core\Contracts\Gateways\Discount\CouponFailureEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class InvalidCouponException extends BaseException
{
    public function __construct(
        CouponFailureEnum $failure,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        $code = match ($failure) {
            CouponFailureEnum::NOT_FOUND => ErrorCodeEnum::COUPON_NOT_FOUND,
            CouponFailureEnum::INACTIVE => ErrorCodeEnum::COUPON_INACTIVE,
            CouponFailureEnum::NOT_STARTED => ErrorCodeEnum::COUPON_NOT_STARTED,
            CouponFailureEnum::EXPIRED => ErrorCodeEnum::COUPON_EXPIRED,
            CouponFailureEnum::USAGE_LIMIT => ErrorCodeEnum::COUPON_USAGE_LIMIT,
            CouponFailureEnum::PER_USER_LIMIT => ErrorCodeEnum::COUPON_PER_USER_LIMIT,
            CouponFailureEnum::MIN_ORDER_AMOUNT => ErrorCodeEnum::COUPON_MIN_ORDER_AMOUNT,
        };

        parent::__construct($code->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
