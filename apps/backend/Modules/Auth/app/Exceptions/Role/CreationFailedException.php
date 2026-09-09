<?php

namespace Modules\Auth\Exceptions\Role;

use Modules\Auth\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
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
        parent::__construct($code ?? ErrorCodeEnum::ROLE_CREATION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
