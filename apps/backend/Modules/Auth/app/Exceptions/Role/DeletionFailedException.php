<?php

namespace Modules\Auth\Exceptions\Role;

use Modules\Auth\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class DeletionFailedException extends BaseException
{
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        /** @var array<string, mixed> */
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::ROLE_DELETION_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
