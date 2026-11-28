<?php

namespace Modules\Webhooks\Actions\Webhooks;

use Modules\Webhooks\Jobs\DeliverWebhookJob;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Operations\DiscoverDueDeliveriesOperation;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Core\Support\Tenancy\TenantContext;

/**
 * The delivery sweep, two-stage (multi-tenancy §6.7.2 bootstrap): stage 1
 * is a registered central operation reading due delivery ids across
 * stores — reads only; stage 2 dispatches each delivery under its OWN
 * tenant's context, so the job's payload carries the store and every
 * attempt is row-authoritative (§6.2). The job's atomic claim makes
 * duplicate enqueues harmless.
 */
class DiscoverDueDeliveriesAction
{
    public function __construct(private readonly CentralOperations $central) {}

    /**
     * @return int deliveries enqueued
     */
    public function handle(): int
    {
        $leaseCutoff = now()->subSeconds((int) config('webhooks.claim_lease_seconds'));

        /** @var list<array{id: string, tenant_id: int}> $due */
        $due = $this->central->run(new DiscoverDueDeliveriesOperation, static::class.': delivery sweep');

        $enqueued = 0;

        foreach ($due as $delivery) {
            app(TenantContext::class)->runForTenant($delivery['tenant_id'], function () use ($delivery, &$enqueued): void {
                DeliverWebhookJob::dispatch($delivery['id']);
                $enqueued++;
            });
        }

        return $enqueued;
    }

    /** Runs inside CentralOperations::run() — central reads only.
     *
     * @return list<array{id: string, tenant_id: int}>
     */
    public function execute(DiscoverDueDeliveriesOperation $operation): array
    {
        $leaseCutoff = now()->subSeconds((int) config('webhooks.claim_lease_seconds'));

        return WebhookDelivery::query()
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
            ->get([WebhookDeliverySchema::ID, \Modules\Core\Schemas\Shared\TenantColumn::TENANT_ID])
            ->map(static fn ($delivery): array => [
                'id' => (string) $delivery->getKey(),
                'tenant_id' => (int) $delivery->getAttribute(\Modules\Core\Schemas\Shared\TenantColumn::TENANT_ID),
            ])
            ->all();
    }
}
