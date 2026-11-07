<?php

namespace Modules\Webhooks\Actions\Webhooks;

use Modules\Webhooks\Jobs\DeliverWebhookJob;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;

/**
 * Responsibility B (gap-C2 D3): the delivery table is the durable source
 * of truth — this sweep enqueues due deliveries (pending, or retryable
 * past their backoff) whose lease is not held. The job's atomic claim
 * makes duplicate enqueues harmless: exactly one worker delivers.
 */
class DispatchDueDeliveriesAction
{
    /**
     * @return int deliveries enqueued
     */
    public function handle(): int
    {
        $leaseCutoff = now()->subSeconds((int) config('webhooks.claim_lease_seconds'));

        $due = WebhookDelivery::query()
            ->whereIn(WebhookDeliverySchema::STATUS, ['pending', 'failed_retryable'])
            ->where(function ($query) use ($leaseCutoff): void {
                $query
                    ->whereNull(WebhookDeliverySchema::CLAIM_TOKEN)
                    ->orWhere(WebhookDeliverySchema::CLAIMED_AT, '<', $leaseCutoff);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull(WebhookDeliverySchema::NEXT_ATTEMPT_AT)
                    ->orWhere(WebhookDeliverySchema::NEXT_ATTEMPT_AT, '<=', now());
            })
            ->limit(100)
            ->pluck(WebhookDeliverySchema::ID);

        foreach ($due as $deliveryId) {
            DeliverWebhookJob::dispatch($deliveryId);
        }

        return $due->count();
    }
}
