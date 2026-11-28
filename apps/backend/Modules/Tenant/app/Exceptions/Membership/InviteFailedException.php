<?php

namespace Modules\Tenant\Exceptions\Membership;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** A membership could not be invited. */
class InviteFailedException extends TenantException
{
    public function __construct(?string $code = null, int $httpStatus = 400, array $meta = [], bool $isSafe = true, ?Throwable $previous = null)
    {
        parent::__construct($code ?? ErrorCodeEnum::MEMBERSHIP_INVITE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
