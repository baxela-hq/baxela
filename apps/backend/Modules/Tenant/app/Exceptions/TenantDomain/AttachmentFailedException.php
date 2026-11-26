<?php

namespace Modules\Tenant\Exceptions\TenantDomain;

use Modules\Tenant\Exceptions\ErrorCodeEnum;
use Modules\Tenant\Exceptions\TenantException;
use Throwable;

/** A domain could not be attached to the store. */
class AttachmentFailedException extends TenantException
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
        parent::__construct($code ?? ErrorCodeEnum::DOMAIN_ATTACHMENT_FAILED->value, $httpStatus, $meta, $isSafe, $previous);
    }
}
