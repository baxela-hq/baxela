<?php

namespace Modules\Cart\Exceptions\User\Checkout;

use Modules\Cart\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class OutOfStockException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::CHECKOUT_OUT_OF_STOCK->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
