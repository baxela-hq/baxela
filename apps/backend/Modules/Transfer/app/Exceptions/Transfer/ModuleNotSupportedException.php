<?php

namespace Modules\Transfer\Exceptions\Transfer;

use Modules\Core\Exceptions\BaseException;
use Modules\Transfer\Exceptions\ErrorCodeEnum;
use Throwable;

class ModuleNotSupportedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 404,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::EXPORT_MODULE_UNKNOWN->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
