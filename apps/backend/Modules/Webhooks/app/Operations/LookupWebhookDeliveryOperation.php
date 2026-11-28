<?php

namespace Modules\Webhooks\Operations;

use Modules\Core\Support\Tenancy\CentralOperation;

/**
 * Bootstrap lookup of one delivery by primary key (multi-tenancy §6.2,
 * the central-dispatch branch): a read-only central read that hands the
 * row's own tenant id to the runForTenant body. No work happens centrally.
 */
final class LookupWebhookDeliveryOperation implements CentralOperation
{
    public function __construct(public readonly string $deliveryId) {}

    public function id(): string
    {
        return 'webhook.delivery.lookup';
    }
}
