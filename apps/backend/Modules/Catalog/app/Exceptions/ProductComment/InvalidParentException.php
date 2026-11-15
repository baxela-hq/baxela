<?php

namespace Modules\Catalog\Exceptions\ProductComment;

use Modules\Catalog\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class InvalidParentException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::PRODUCT_COMMENT_INVALID_PARENT->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
