<?php

namespace Modules\Tax\Exceptions\TaxClass;

use Modules\Core\Exceptions\BaseException;
use Modules\Tax\Exceptions\ErrorCodeEnum;
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
        parent::__construct($code ?? ErrorCodeEnum::CLASS_UPDATE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
