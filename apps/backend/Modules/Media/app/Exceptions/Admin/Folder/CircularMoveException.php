<?php

namespace Modules\Media\Exceptions\Admin\Folder;

use Modules\Core\Exceptions\BaseException;
use Modules\Media\Exceptions\ErrorCodeEnum;
use Throwable;

class CircularMoveException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 422,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::FOLDER_CIRCULAR_MOVE->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
