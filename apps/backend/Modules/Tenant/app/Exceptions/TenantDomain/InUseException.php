<?php

namespace Modules\Tenant\Exceptions\TenantDomain;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The domain belongs to another store. */
class InUseException extends TenantException
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
        parent::__construct($code ?? ErrorCodeEnum::DOMAIN_IN_USE->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
