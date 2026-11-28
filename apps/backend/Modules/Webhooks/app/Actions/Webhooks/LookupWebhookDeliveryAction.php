<?php

namespace Modules\Webhooks\Actions\Webhooks;

use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Operations\LookupWebhookDeliveryOperation;

/**
 * The central-dispatch branch's bootstrap lookup (multi-tenancy §6.2): a
 * read-only central read of ONE delivery by primary key, handing the row's
 * own tenant id to the runForTenant body. Runs inside
 * CentralOperations::run().
 */
class LookupWebhookDeliveryAction
{
    /** Runs inside CentralOperations::run(). */
    public function execute(LookupWebhookDeliveryOperation $operation): ?WebhookDelivery
    {
        return WebhookDelivery::query()->find($operation->deliveryId);
    }
}
