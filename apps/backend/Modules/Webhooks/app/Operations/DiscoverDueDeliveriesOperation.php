<?php

namespace Modules\Webhooks\Operations;

use Modules\Core\Support\Tenancy\CentralOperation;

/**
 * Bootstrap stage 1 of the delivery sweep (multi-tenancy §6.7.2): a
 * read-only central read of due delivery ids across stores. Central mode
 * performs reads only — the per-delivery work happens under the record's
 * own tenant (stage 2, the job's context).
 */
final class DiscoverDueDeliveriesOperation implements CentralOperation
{
    public function id(): string
    {
        return 'webhook.due-deliveries.discover';
    }
}
