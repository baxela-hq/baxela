<?php

namespace Modules\Webhooks\Http\Controllers\Admin\WebhookDelivery;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Webhooks\Exceptions\WebhooksException;
use Modules\Webhooks\Jobs\DeliverWebhookJob;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliveryStatusEnum;

/**
 * Operator recovery from exhausted retries (gap-C2 D13): only eligible
 * terminal failures are retried; the original event/delivery ids are
 * preserved (a fresh claim on the same row).
 */
class RetryWebhookDeliveryController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $delivery = WebhookDelivery::query()->find($id);

        if (is_null($delivery)) {
            throw WebhooksException::deliveryNotFound();
        }

        if ($delivery->{WebhookDeliverySchema::STATUS} !== WebhookDeliveryStatusEnum::FAILED_PERMANENT) {
            throw WebhooksException::deliveryNotRetryable();
        }

        WebhookDelivery::query()
            ->where(WebhookDeliverySchema::ID, $delivery->getKey())
            ->update([
                WebhookDeliverySchema::STATUS => WebhookDeliveryStatusEnum::FAILED_RETRYABLE->value,
                WebhookDeliverySchema::NEXT_ATTEMPT_AT => now(),
                WebhookDeliverySchema::CLAIM_TOKEN => null,
            ]);

        DeliverWebhookJob::dispatch($delivery->getKey());

        return response()->json(['data' => ['retried' => true]]);
    }
}
