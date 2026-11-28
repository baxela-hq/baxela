<?php

namespace Modules\Webhooks\Events;

use Modules\Core\Contracts\Events\AbstractBaseEvent;
use Modules\Core\Contracts\Events\MustBeAudited;

/**
 * A delivery for a suspended store was skipped before its claim (§3.1):
 * the delivery stays pending and is re-dispatched after reactivation —
 * audited so the skip is visible, never silent.
 */
class WebhookDeliverySkippedEvent extends AbstractBaseEvent implements MustBeAudited
{
    public function __construct(
        public string $delivery_id,
        public int $tenant_id,
    ) {}
}
