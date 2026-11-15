<?php

namespace Modules\Discount\Exceptions;

use Modules\Core\Exceptions\BaseException;
use Throwable;

class DiscountException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code, $httpStatus, $meta, $isSafe, $previous);
    }
}
