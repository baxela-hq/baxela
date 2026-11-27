<?php

namespace Modules\Webhooks\Actions\Webhooks;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantWriteStamp;
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

                $deliveryId = (string) Str::uuid();

                $values = [
                    WebhookDeliverySchema::ID => $deliveryId,
                    WebhookDeliverySchema::EVENT_ID => $event->{WebhookEventSchema::ID},
                    WebhookDeliverySchema::ENDPOINT_ID => $endpoint->getKey(),
                    WebhookDeliverySchema::EVENT_TYPE => $event->{WebhookEventSchema::EVENT_TYPE},
                    WebhookDeliverySchema::STATUS => 'pending',
                    WebhookDeliverySchema::CREATED_AT => now(),
                ];

                // The event payload is caller data: never let it choose the
                // owner. Drop any supplied stamp before stamping from the
                // active tenant context.
                $values = Arr::except($values, [TenantColumn::TENANT_ID]);
                $values[TenantColumn::TENANT_ID] = TenantWriteStamp::tenantIdForWrite();

                // Scoped conflict (postgres-migration DD13): only the
                // intended (endpoint_id, event_id) duplicate is ignored —
                // a targetless ON CONFLICT DO NOTHING on PostgreSQL would
                // swallow ANY unique violation. The delivery re-fetch
                // (not the affected-row count) is the existence truth.
                $inserted = DB::connection()->getDriverName() === 'pgsql'
                    ? DB::affectingStatement(
                        'INSERT INTO '.WebhookDeliverySchema::TABLE
                        .' (id, '
                        .WebhookDeliverySchema::EVENT_ID.', '
                        .WebhookDeliverySchema::ENDPOINT_ID.', '
                        .WebhookDeliverySchema::EVENT_TYPE.', '
                        .WebhookDeliverySchema::STATUS.', '
                        .WebhookDeliverySchema::CREATED_AT.', '
                        .TenantColumn::TENANT_ID.')'
                        .' VALUES (?, ?, ?, ?, ?, ?, ?)'
                        .' ON CONFLICT ('.WebhookDeliverySchema::ENDPOINT_ID.', '.WebhookDeliverySchema::EVENT_ID.') DO NOTHING',
                        [
                            $deliveryId,
                            $event->{WebhookEventSchema::ID},
                            $endpoint->getKey(),
                            $event->{WebhookEventSchema::EVENT_TYPE},
                            'pending',
                            $values[WebhookDeliverySchema::CREATED_AT],
                            $values[TenantColumn::TENANT_ID],
                        ],
                    )
                    : WebhookDelivery::query()->insertOrIgnore($values);

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
