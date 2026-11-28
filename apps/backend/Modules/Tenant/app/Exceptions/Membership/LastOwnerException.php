<?php

namespace Modules\Tenant\Exceptions\Membership;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/**
 * The mutation would leave the store without an active owner (§6.5, F4):
 * removing, suspending or demoting the only owner is rejected.
 */
class LastOwnerException extends TenantException
{
    public function __construct(?string $code = null, int $httpStatus = 409, array $meta = [], bool $isSafe = true, ?Throwable $previous = null)
    {
        parent::__construct($code ?? ErrorCodeEnum::MEMBERSHIP_LAST_OWNER->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
