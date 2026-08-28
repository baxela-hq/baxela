<?php

namespace Modules\Auth\Exceptions;

use Modules\Core\Exceptions\BaseException;

class OtpTooManyRequestsException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?\Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::OTP_TOO_MANY_REQUESTS->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
