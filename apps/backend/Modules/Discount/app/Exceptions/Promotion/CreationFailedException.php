<?php

namespace Modules\Discount\Exceptions\Promotion;

use Modules\Core\Exceptions\BaseException;
use Modules\Discount\Exceptions\ErrorCodeEnum;
use Throwable;

class CreationFailedException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::PROMOTION_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
