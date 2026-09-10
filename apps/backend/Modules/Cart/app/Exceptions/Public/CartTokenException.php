<?php

namespace Modules\Cart\Exceptions\Public;

use Modules\Cart\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class CartTokenException extends BaseException
{
    public function __construct(
        bool $missing,
        int $httpStatus = 400,
        bool $isSafe = true,
        ?Throwable $previous = null,
    ) {
        $code = $missing
            ? ErrorCodeEnum::TOKEN_MISSING->value
            : ErrorCodeEnum::TOKEN_INVALID->value;

        parent::__construct($code, $httpStatus, [], $isSafe, $previous);
    }
}
