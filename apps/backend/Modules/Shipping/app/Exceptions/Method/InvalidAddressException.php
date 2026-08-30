<?php

namespace Modules\Shipping\Exceptions\Method;

use Modules\Core\Exceptions\BaseException;
use Modules\Shipping\Exceptions\ErrorCodeEnum;
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
        parent::__construct($code ?? ErrorCodeEnum::METHOD_INVALID_ADDRESS->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
