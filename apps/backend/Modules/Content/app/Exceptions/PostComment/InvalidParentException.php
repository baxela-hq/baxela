<?php

namespace Modules\Content\Exceptions\PostComment;

use Modules\Content\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class InvalidParentException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::POST_COMMENT_INVALID_PARENT->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
