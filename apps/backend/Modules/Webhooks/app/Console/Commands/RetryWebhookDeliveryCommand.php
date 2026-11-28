<?php

namespace Modules\Webhooks\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Webhooks\Jobs\DeliverWebhookJob;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Operations\LookupWebhookDeliveryOperation;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliveryStatusEnum;

/**
 * Operator recovery from exhausted retries (gap-C2 D13): only eligible
 * terminal failures. Bootstrap mode (multi-tenancy §6.7.2): the central
 * lookup by primary key reads across stores; every write and the dispatch
 * happen under the delivery's own tenant.
 */
class RetryWebhookDeliveryCommand extends Command
{
    protected $signature = 'webhooks:retry-delivery {delivery_id}';

    protected $description = 'Retry an eligible terminal-failed webhook delivery';

    public function handle(CentralOperations $central): int
    {
        /** @var \Modules\Webhooks\Models\WebhookDelivery|null $delivery */
        $delivery = $central->run(new LookupWebhookDeliveryOperation((string) $this->argument('delivery_id')), static::class.': retry lookup');

        if (is_null($delivery)) {
            $this->error('Delivery not found.');

            return self::FAILURE;
        }

        if ($delivery->{WebhookDeliverySchema::STATUS} !== WebhookDeliveryStatusEnum::FAILED_PERMANENT) {
            $this->error('Only permanent failures are retryable.');

            return self::FAILURE;
        }

        $tenantId = (int) $delivery->getAttribute(\Modules\Core\Schemas\Shared\TenantColumn::TENANT_ID);

        app(TenantContext::class)->runForTenant($tenantId, function () use ($delivery): void {
            WebhookDelivery::query()
                ->where(WebhookDeliverySchema::ID, $delivery->getKey())
                ->update([
                    WebhookDeliverySchema::STATUS => WebhookDeliveryStatusEnum::FAILED_RETRYABLE->value,
                    WebhookDeliverySchema::NEXT_ATTEMPT_AT => now(),
                    WebhookDeliverySchema::CLAIM_TOKEN => null,
                ]);

            DeliverWebhookJob::dispatch($delivery->getKey());
        });

        $this->info('Delivery re-enqueued.');

        return self::SUCCESS;
    }
}
