<?php

namespace Modules\Discount\Exceptions\Coupon;

use Modules\Core\Exceptions\BaseException;
use Modules\Discount\Exceptions\ErrorCodeEnum;
use Throwable;

class HasRedemptionsException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::COUPON_HAS_REDEMPTIONS->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
