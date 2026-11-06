<?php

namespace Modules\Transfer\Exceptions\Transfer;

use Modules\Core\Exceptions\BaseException;
use Modules\Transfer\Exceptions\ErrorCodeEnum;
use Throwable;

class ImportFailedException extends BaseException
{
    public function __construct(
        $code = null,
        int $httpStatus = 422,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::IMPORT_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
