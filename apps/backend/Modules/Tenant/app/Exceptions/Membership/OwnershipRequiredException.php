<?php

namespace Modules\Tenant\Exceptions\Membership;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** Ownership transfer requires the actor to be the store's owner or the platform super-admin (§6.5 Category 2). */
class OwnershipRequiredException extends TenantException
{
    public function __construct(?string $code = null, int $httpStatus = 403, array $meta = [], bool $isSafe = true, ?Throwable $previous = null)
    {
        parent::__construct($code ?? ErrorCodeEnum::MEMBERSHIP_OWNERSHIP_REQUIRED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
