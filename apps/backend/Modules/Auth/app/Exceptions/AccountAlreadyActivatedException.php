<?php

namespace Modules\Auth\Exceptions;

use Modules\Core\Exceptions\BaseException;

class AccountAlreadyActivatedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?\Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::AUTH_ACCOUNT_ALREADY_ACTIVATED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
