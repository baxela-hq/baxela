<?php

namespace Modules\Core\Exceptions;

use Throwable;

/**
 * An idempotent request has neither an authenticated principal nor the
 * guest cart token that identifies it (multi-tenancy §6.6). Failing closed
 * is safer than collapsing every anonymous caller into one principal.
 */
class IdempotencyPrincipalMissingException extends BaseException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('core.idempotency.principal_missing', 422, [], true, $previous);
    }
}
