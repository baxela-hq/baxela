<?php

namespace Modules\Webhooks\Console\Commands;

use Illuminate\Console\Command;
use Modules\Webhooks\Jobs\DeliverWebhookJob;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliveryStatusEnum;

/**
 * Operator recovery from exhausted retries (gap-C2 D13): only eligible
 * terminal failures; the original event/delivery ids are preserved.
 */
class RetryWebhookDeliveryCommand extends Command
{
    protected $signature = 'webhooks:retry-delivery {delivery_id}';

    protected $description = 'Retry an eligible terminal-failed webhook delivery';

    public function handle(): int
    {
        $delivery = WebhookDelivery::query()->find($this->argument('delivery_id'));

        if (is_null($delivery)) {
            $this->error('Delivery not found.');

            return self::FAILURE;
        }

        if ($delivery->{WebhookDeliverySchema::STATUS} !== WebhookDeliveryStatusEnum::FAILED_PERMANENT) {
            $this->error('Only permanent failures are retryable.');

            return self::FAILURE;
        }

        WebhookDelivery::query()
            ->where(WebhookDeliverySchema::ID, $delivery->getKey())
            ->update([
                WebhookDeliverySchema::STATUS => WebhookDeliveryStatusEnum::FAILED_RETRYABLE->value,
                WebhookDeliverySchema::NEXT_ATTEMPT_AT => now(),
                WebhookDeliverySchema::CLAIM_TOKEN => null,
            ]);

        DeliverWebhookJob::dispatch($delivery->getKey());

        $this->info('Delivery re-enqueued.');

        return self::SUCCESS;
    }
}
