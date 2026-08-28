<?php

namespace Modules\Auth\Exceptions;

use Modules\Core\Exceptions\BaseException;

class InvalidOtpException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?\Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::OTP_INVALID->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
