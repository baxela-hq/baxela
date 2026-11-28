<?php

namespace Modules\Tenant\Exceptions\Membership;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The user already holds a membership in the active store. */
class AlreadyMemberException extends TenantException
{
    public function __construct(?string $code = null, int $httpStatus = 409, array $meta = [], bool $isSafe = true, ?Throwable $previous = null)
    {
        parent::__construct($code ?? ErrorCodeEnum::MEMBERSHIP_ALREADY_MEMBER->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
