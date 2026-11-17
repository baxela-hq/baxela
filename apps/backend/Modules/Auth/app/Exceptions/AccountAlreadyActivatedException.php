<?php

namespace Modules\Auth\Exceptions;

use Modules\Core\Exceptions\BaseException;

class AccountAlreadyActivatedException extends BaseException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?\Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::AUTH_ACCOUNT_ALREADY_ACTIVATED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
