<?php

namespace Modules\Cart\Exceptions\User\Checkout;

use Modules\Cart\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class EmptyCardException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::CHECKOUT_EMPTY->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
