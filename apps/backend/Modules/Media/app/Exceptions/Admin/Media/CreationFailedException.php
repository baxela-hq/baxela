<?php

namespace Modules\Media\Exceptions\Admin\Media;

use Modules\Core\Exceptions\BaseException;
use Modules\Media\Exceptions\ErrorCodeEnum;
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
        parent::__construct($code ?? ErrorCodeEnum::CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
