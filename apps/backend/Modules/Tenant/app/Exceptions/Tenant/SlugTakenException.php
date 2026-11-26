<?php

namespace Modules\Tenant\Exceptions\Tenant;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The slug is already taken by another store. */
class SlugTakenException extends TenantException
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
        parent::__construct($code ?? ErrorCodeEnum::TENANT_SLUG_TAKEN->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
