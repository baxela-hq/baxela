<?php

namespace Modules\Content\Exceptions\Post;

use Modules\Content\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class UpdateFailedException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::POST_UPDATE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
