<?php

namespace Modules\Tenant\Exceptions\Tenant;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The tenant's status could not be changed. */
class StatusChangeFailedException extends TenantException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        ?string $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::TENANT_STATUS_CHANGE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
