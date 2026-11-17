<?php

namespace Modules\Content\Exceptions\PostCategory;

use Modules\Content\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class UpdateFailedException extends BaseException
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
        parent::__construct($code ?? ErrorCodeEnum::POST_CATEGORY_UPDATE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
