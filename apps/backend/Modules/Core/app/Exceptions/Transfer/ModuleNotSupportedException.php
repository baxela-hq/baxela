<?php

namespace Modules\Core\Exceptions\Transfer;

use Modules\Core\Exceptions\BaseException;
use Throwable;

class ModuleNotSupportedException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 404,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::EXPORT_MODULE_UNKNOWN->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
