<?php

namespace Modules\Tenant\Exceptions\Membership;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The member's role set within the store could not be replaced. */
class RoleSyncFailedException extends TenantException
{
    public function __construct(?string $code = null, int $httpStatus = 400, array $meta = [], bool $isSafe = true, ?Throwable $previous = null)
    {
        parent::__construct($code ?? ErrorCodeEnum::MEMBERSHIP_ROLE_SYNC_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
