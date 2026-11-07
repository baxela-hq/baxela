<?php

namespace Modules\Webhooks\Actions\Webhooks;

use Illuminate\Support\Str;
use Modules\Webhooks\Models\WebhookEvent;
use Modules\Webhooks\Schemas\WebhookEvent\WebhookEventSchema;
use Modules\Webhooks\Support\EventCatalog;

/**
 * Writes the outbox row for one business transition (gap-C2 D2). Called
 * by SYNCHRONOUS listeners: when the event fires inside a business
 * transaction, this insert joins that transaction — a crash before
 * commit rolls back the event and its outbox row together, so no
 * webhook is ever emitted for an uncommitted change. source_key dedupes
 * repeated invocations of the same transition.
 */
class WriteOutboxEventAction
{
    public function write(string $type, string $sourceKey, array $data): WebhookEvent
    {
        $payload = [
            'api_version' => EventCatalog::API_VERSION,
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        return WebhookEvent::query()->firstOrCreate(
            [WebhookEventSchema::SOURCE_KEY => $sourceKey],
            [
                WebhookEventSchema::ID => (string) Str::uuid(),
                WebhookEventSchema::EVENT_TYPE => $type,
                WebhookEventSchema::PAYLOAD => $payload,
            ],
        );
    }
}
