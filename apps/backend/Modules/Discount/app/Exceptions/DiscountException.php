<?php

namespace Modules\Discount\Exceptions;

use Modules\Core\Exceptions\BaseException;
use Throwable;

class DiscountException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code, $httpStatus, $meta, $isSafe, $previous);
    }
}
