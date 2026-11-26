<?php

namespace Modules\Tenant\Exceptions\TenantDomain;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The store's domain could not be released. */
class ReleaseFailedException extends TenantException
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
        parent::__construct($code ?? ErrorCodeEnum::DOMAIN_RELEASE_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
