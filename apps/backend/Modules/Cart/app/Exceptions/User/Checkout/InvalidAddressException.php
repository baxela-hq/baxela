<?php

namespace Modules\Cart\Exceptions\User\Checkout;

use Modules\Cart\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class InvalidAddressException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::CHECKOUT_INVALID_ADDRESS->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
