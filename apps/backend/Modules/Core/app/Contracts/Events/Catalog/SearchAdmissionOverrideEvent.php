<?php

namespace Modules\Core\Contracts\Events\Catalog;

use Modules\Core\Contracts\Events\AbstractBaseEvent;
use Modules\Core\Contracts\Events\MustBeAudited;

/**
 * An operator released a search worker's open admissions by decree
 * (postgres-migration §3.2.2, `catalog:search:assume-dead`) — for a
 * worker whose death /proc evidence cannot prove. Audited on EVERY
 * outcome, including the ones that release nothing: the trail must show
 * the refused attempts too.
 *
 * The payload key is deliberately `instance_token`, not `token`: the
 * audit redaction blanks any key named `token`, and the token IS the
 * record of what was acted on.
 */
class SearchAdmissionOverrideEvent extends AbstractBaseEvent implements MustBeAudited
{
    public function __construct(
        public string $instance_token,
        /** released | superseded | not-found */
        public string $outcome,
        public int $released,
        public string $reason,
        public string $operator,
        public ?string $boot_id,
        public ?int $pid,
        public ?string $process_start_time,
        public string $occurred_at,
    ) {}
}
