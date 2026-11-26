<?php

namespace Modules\Tenant\Exceptions\TenantDomain;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The domain was released recently and is still cooling off. */
class CoolingOffException extends TenantException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        ?string $code = null,
        int $httpStatus = 409,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::DOMAIN_COOLING_OFF->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
