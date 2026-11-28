<?php

namespace Modules\Core\Exceptions;

use Throwable;

/**
 * Another request owns this idempotency key and its claim has not expired
 * (multi-tenancy §6.6). A safe 409: no business work ran for this request.
 */
class IdempotencyInProgressException extends BaseException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('core.idempotency.in_progress', 409, [], true, $previous);
    }
}
