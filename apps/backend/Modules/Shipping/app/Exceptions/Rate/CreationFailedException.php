<?php

namespace Modules\Shipping\Exceptions\Rate;

use Modules\Core\Exceptions\BaseException;
use Modules\Shipping\Exceptions\ErrorCodeEnum;
use Throwable;

class CreationFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::RATE_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
