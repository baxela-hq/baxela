<?php

namespace Modules\Tenant\Exceptions\Onboarding;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The store could not be provisioned. */
class ProvisioningFailedException extends TenantException
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
        parent::__construct($code ?? ErrorCodeEnum::ONBOARDING_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
