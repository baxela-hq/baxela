<?php

namespace Modules\Webhooks\Actions\Webhooks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Models\WebhookEvent;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;
use Modules\Webhooks\Schemas\WebhookEvent\WebhookEventSchema;

/**
 * Consumes one outbox event: resolves the endpoints subscribed to its
 * type, creates one delivery per endpoint, marks the event processed —
 * in one transaction (gap-C2 D3, responsibility A). The delivery rows
 * are the durable source of truth for pending work; the scheduled sweep
 * enqueues them.
 */
class ProcessOutboxEventAction
{
    /**
     * @return int deliveries created
     */
    public function handle(WebhookEvent $event): int
    {
        if ($event->{WebhookEventSchema::PROCESSED_AT} !== null) {
            return 0;
        }

        $created = DB::transaction(function () use ($event): int {
            $endpoints = WebhookEndpoint::query()
                ->where(WebhookEndpointSchema::IS_ACTIVE, true)
                ->where(WebhookEndpointSchema::IS_ARCHIVED, false)
                ->get();

            $created = 0;

            foreach ($endpoints as $endpoint) {
                if (! in_array($event->{WebhookEventSchema::EVENT_TYPE}, (array) $endpoint->{WebhookEndpointSchema::EVENTS}, true)) {
                    continue;
                }

                // insertOrIgnore + the (endpoint_id, event_id) unique make
                // concurrent processors safe (gap-C2 D4).
                $inserted = WebhookDelivery::query()->insertOrIgnore([
                    WebhookDeliverySchema::ID => (string) Str::uuid(),
                    WebhookDeliverySchema::EVENT_ID => $event->{WebhookEventSchema::ID},
                    WebhookDeliverySchema::ENDPOINT_ID => $endpoint->getKey(),
                    WebhookDeliverySchema::EVENT_TYPE => $event->{WebhookEventSchema::EVENT_TYPE},
                    WebhookDeliverySchema::STATUS => 'pending',
                    WebhookDeliverySchema::CREATED_AT => now(),
                ]);

                $created += (int) $inserted;
            }

            $event->forceFill([
                WebhookEventSchema::PROCESSED_AT => now(),
            ])->save();

            return $created;
        });

        return $created;
    }
}
