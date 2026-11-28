<?php

namespace Modules\Core\Exceptions;

use Throwable;

/**
 * The claim's business work committed but its response could not be stored
 * (or the claimant lost ownership after committing). The key must never be
 * re-executed; it stays for operator recovery (multi-tenancy §6.6).
 */
class IdempotencyUnresolvedException extends BaseException
{
    /** @param array<string, mixed> $meta */
    public function __construct(array $meta = [], ?Throwable $previous = null)
    {
        parent::__construct('core.idempotency.unresolved', 409, $meta, true, $previous);
    }
}
