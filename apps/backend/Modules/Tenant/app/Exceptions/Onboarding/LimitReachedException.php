<?php

namespace Modules\Tenant\Exceptions\Onboarding;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** The user already owns the maximum number of stores. */
class LimitReachedException extends TenantException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        ?string $code = null,
        int $httpStatus = 422,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($code ?? ErrorCodeEnum::ONBOARDING_LIMIT_REACHED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
