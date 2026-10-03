<?php

namespace Modules\Content\Exceptions\PostCategory;

use Modules\Content\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class CreationFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::POST_CATEGORY_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
