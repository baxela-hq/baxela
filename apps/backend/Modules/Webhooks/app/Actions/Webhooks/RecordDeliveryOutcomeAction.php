<?php

namespace Modules\Webhooks\Actions\Webhooks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;

/**
 * Applies a terminal delivery outcome to the endpoint's consecutive
 * failure counter (gap-C2 D11): success resets to zero, failure
 * increments — inside the endpoint lock so concurrent completions apply
 * in a defined order (terminal-state order, not event chronology).
 * At the configured threshold the endpoint auto-disables.
 */
class RecordDeliveryOutcomeAction
{
    public function handle(WebhookDelivery $delivery, bool $success): void
    {
        $endpoint = WebhookEndpoint::query()->find($delivery->{WebhookDeliverySchema::ENDPOINT_ID});

        if (is_null($endpoint)) {
            return;
        }

        $lock = Cache::lock('webhook-endpoint:'.$endpoint->getKey(), 10);

        if (! $lock->get()) {
            // Contention is rare and non-fatal: the sweep re-delivers
            // retryable outcomes, so a skipped counter update only delays
            // auto-disable by one cycle.
            return;
        }

        try {
            DB::transaction(function () use ($endpoint, $success): void {
                $fresh = WebhookEndpoint::query()
                    ->whereKey($endpoint->getKey())
                    ->lockForUpdate()
                    ->first();

                if (is_null($fresh)) {
                    return;
                }

                if ($success) {
                    $fresh->forceFill([
                        WebhookEndpointSchema::CONSECUTIVE_FAILURES => 0,
                    ])->save();

                    return;
                }

                $failures = $fresh->{WebhookEndpointSchema::CONSECUTIVE_FAILURES} + 1;
                $fresh->forceFill([
                    WebhookEndpointSchema::CONSECUTIVE_FAILURES => $failures,
                    WebhookEndpointSchema::IS_ACTIVE => $failures < (int) config('webhooks.failure_threshold'),
                ])->save();
            });
        } finally {
            $lock->release();
        }
    }
}
