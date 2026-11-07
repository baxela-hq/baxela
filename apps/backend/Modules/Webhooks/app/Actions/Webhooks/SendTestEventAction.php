<?php

namespace Modules\Webhooks\Actions\Webhooks;

use Modules\Webhooks\Exceptions\WebhooksException;
use Modules\Webhooks\Jobs\DeliverWebhookJob;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;

/**
 * Synthetic `test` event through the real pipeline (gap-C2 D13): outbox
 * row → deliveries → job, so the merchant validates the exact production
 * path.
 */
class SendTestEventAction
{
    public function __construct(
        protected WriteOutboxEventAction $writer,
        protected ProcessOutboxEventAction $processor,
    ) {}

    /**
     * @return int deliveries created
     *
     * @throws WebhooksException when the endpoint does not exist
     */
    public function handle(string $endpointId): int
    {
        $endpoint = WebhookEndpoint::query()->find($endpointId);

        if (is_null($endpoint)) {
            throw WebhooksException::endpointNotFound();
        }

        $type = 'test';

        $event = $this->writer->write(
            type: $type,
            sourceKey: sprintf('%s:%d:%s', $type, $endpoint->getKey(), now()->format('YmdHisu')),
            payload: [
                'endpoint_id' => $endpoint->getKey(),
                'endpoint_name' => $endpoint->{WebhookEndpointSchema::NAME},
            ],
        );

        $created = $this->processor->handle($event);

        // Deliver immediately so the merchant sees the ping without
        // waiting for the sweep.
        $event->refresh()->deliveries()->each(
            fn ($delivery) => DeliverWebhookJob::dispatch($delivery->{WebhookDeliverySchema::ID}),
        );

        return $created;
    }
}
