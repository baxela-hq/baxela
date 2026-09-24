<?php

namespace Modules\Discount\Exceptions\Coupon;

use Modules\Core\Exceptions\BaseException;
use Modules\Discount\Exceptions\ErrorCodeEnum;
use Throwable;

class UpdateFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::COUPON_UPDATE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
