<?php

namespace Modules\Tax\Exceptions\TaxClass;

use Modules\Core\Exceptions\BaseException;
use Modules\Tax\Exceptions\ErrorCodeEnum;
use Throwable;

class DeleteDefaultException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::CLASS_DELETE_DEFAULT->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
