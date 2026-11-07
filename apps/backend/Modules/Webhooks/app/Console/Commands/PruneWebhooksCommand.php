<?php

namespace Modules\Webhooks\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookEvent\WebhookEventSchema;

/**
 * Retention (gap-C2 D12): terminal deliveries and fully-terminal outbox
 * events older than the configured horizon are pruned — FK-ordered
 * (deliveries first, then events with none left).
 */
class PruneWebhooksCommand extends Command
{
    protected $signature = 'webhooks:prune';

    protected $description = 'Prune old webhook deliveries and fully-terminal outbox events';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('webhooks.retention_days'));

        $deliveries = DB::table(WebhookDeliverySchema::TABLE)
            ->whereIn(WebhookDeliverySchema::STATUS, ['succeeded', 'failed_permanent'])
            ->where(WebhookDeliverySchema::CREATED_AT, '<', $cutoff)
            ->delete();

        // Events whose last delivery is gone (FK cascades handled the
        // rest) and that themselves are older than the horizon.
        $events = DB::table(WebhookEventSchema::TABLE)
            ->where(WebhookEventSchema::CREATED_AT, '<', $cutoff)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from(WebhookDeliverySchema::TABLE)
                    ->whereColumn(
                        WebhookDeliverySchema::TABLE.'.'.WebhookDeliverySchema::EVENT_ID,
                        WebhookEventSchema::TABLE.'.'.WebhookEventSchema::ID,
                    );
            })
            ->delete();

        $this->info("Pruned {$deliveries} deliveries and {$events} events.");

        return self::SUCCESS;
    }
}
