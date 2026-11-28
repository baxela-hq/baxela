<?php

namespace Modules\Core\Contracts\Events\Tenant;

use Modules\Core\Contracts\Events\AbstractBaseEvent;
use Modules\Core\Contracts\Events\MustBeAudited;

/**
 * A committed idempotency claim has no stored response. Audited for
 * operator review (multi-tenancy §6.6, rows 76/97/111); the raw key and
 * claim token are deliberately not part of the payload.
 */
class IdempotencyUnresolvedEvent extends AbstractBaseEvent implements MustBeAudited
{
    public function __construct(
        public int $claim_id,
        public int $tenant_id,
        public string $reason,
    ) {}
}
