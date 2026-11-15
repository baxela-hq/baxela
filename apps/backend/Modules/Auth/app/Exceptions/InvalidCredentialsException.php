<?php

namespace Modules\Auth\Exceptions;

use Modules\Core\Exceptions\BaseException;

class InvalidCredentialsException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 401,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?\Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::AUTH_INVALID_CREDENTIALS->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
