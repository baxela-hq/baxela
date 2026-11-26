<?php

namespace Modules\Core\Contracts\Events\Tenant;

use Modules\Core\Contracts\Events\AbstractBaseEvent;
use Modules\Core\Contracts\Events\MustBeAudited;

/**
 * A registered central operation ran, or was attempted (multi-tenancy
 * §6.5). Audited on every outcome, refused attempts included — the trail
 * must show who ran what across stores, and why.
 *
 * MustBeAudited: on success the record is written inside the operation's
 * transaction, so an operation can never take effect unaudited.
 */
class CentralOperationRunEvent extends AbstractBaseEvent implements MustBeAudited
{
    public function __construct(
        public string $operation,
        public string $operation_id,
        public string $reason,
        public string $caller,
        /** succeeded | failed */
        public string $outcome,
        public ?int $actor_id,
    ) {}
}
