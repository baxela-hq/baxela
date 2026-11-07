<?php

namespace Modules\Core\Exceptions\Transfer;

use Modules\Core\Exceptions\BaseException;
use Throwable;

class ExportFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::EXPORT_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
