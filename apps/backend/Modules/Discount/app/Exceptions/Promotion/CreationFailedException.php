<?php

namespace Modules\Discount\Exceptions\Promotion;

use Modules\Core\Exceptions\BaseException;
use Modules\Discount\Exceptions\ErrorCodeEnum;
use Throwable;

class CreationFailedException extends BaseException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::PROMOTION_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
