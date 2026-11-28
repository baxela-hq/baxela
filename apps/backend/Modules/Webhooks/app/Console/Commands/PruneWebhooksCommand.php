<?php

namespace Modules\Webhooks\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Models\WebhookEvent;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookEvent\WebhookEventSchema;

/**
 * Retention (gap-C2 D12), tenant-fan-out mode (multi-tenancy §6.7.2): the
 * scheduled command never writes store-owned rows directly — it iterates
 * the stores and every delete runs under that store's scope. Only
 * terminal deliveries are pruned, so suspended stores lose nothing that
 * could still be delivered.
 */
class PruneWebhooksCommand extends Command
{
    protected $signature = 'webhooks:prune';

    protected $description = 'Prune old webhook deliveries and fully-terminal outbox events';

    public function handle(TenantGatewayInterface $tenants, TenantContext $context): int
    {
        $cutoff = now()->subDays((int) config('webhooks.retention_days'));

        $deliveries = 0;
        $events = 0;

        foreach ($tenants->allIds() as $tenantId) {
            $context->runForTenant($tenantId, function () use ($cutoff, &$deliveries, &$events): void {
                $deliveries += WebhookDelivery::query()
                    ->whereIn(WebhookDeliverySchema::STATUS, ['succeeded', 'failed_permanent'])
                    ->where(WebhookDeliverySchema::CREATED_AT, '<', $cutoff)
                    ->delete();

                // Events whose last delivery is gone (FK cascades handled the
                // rest) and that themselves are older than the horizon.
                $events += WebhookEvent::query()
                    ->where(WebhookEventSchema::CREATED_AT, '<', $cutoff)
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw(1)
                            ->from(WebhookDeliverySchema::TABLE)
                            ->whereColumn(
                                WebhookDeliverySchema::TABLE.'.'.WebhookDeliverySchema::EVENT_ID,
                                WebhookEventSchema::TABLE.'.'.WebhookEventSchema::ID,
                            );
                    })
                    ->delete();
            });
        }

        $this->info("Pruned {$deliveries} deliveries and {$events} events.");

        return self::SUCCESS;
    }
}
