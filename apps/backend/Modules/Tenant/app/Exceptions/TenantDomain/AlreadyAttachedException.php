<?php

namespace Modules\Tenant\Exceptions\TenantDomain;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The domain is already attached to this store. */
class AlreadyAttachedException extends TenantException
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
        parent::__construct($code ?? ErrorCodeEnum::DOMAIN_ALREADY_ATTACHED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
