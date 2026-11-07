<?php

namespace Modules\Webhooks\Transformers\Admin\WebhookDelivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Webhooks\Models\WebhookDelivery;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;

/**
 * @mixin WebhookDelivery
 */
class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            WebhookDeliverySchema::ID => $this->resource->getKey(),
            WebhookDeliverySchema::EVENT_ID => $this->resource->{WebhookDeliverySchema::EVENT_ID},
            WebhookDeliverySchema::ENDPOINT_ID => $this->resource->{WebhookDeliverySchema::ENDPOINT_ID},
            WebhookDeliverySchema::EVENT_TYPE => $this->resource->{WebhookDeliverySchema::EVENT_TYPE},
            WebhookDeliverySchema::STATUS => $this->resource->{WebhookDeliverySchema::STATUS},
            WebhookDeliverySchema::ATTEMPT_COUNT => $this->resource->{WebhookDeliverySchema::ATTEMPT_COUNT},
            WebhookDeliverySchema::ATTEMPTS => $this->resource->{WebhookDeliverySchema::ATTEMPTS},
            WebhookDeliverySchema::NEXT_ATTEMPT_AT => $this->resource->{WebhookDeliverySchema::NEXT_ATTEMPT_AT},
            WebhookDeliverySchema::RESPONSE_STATUS => $this->resource->{WebhookDeliverySchema::RESPONSE_STATUS},
            // Sanitized snippets only — never full response bodies or secrets.
            WebhookDeliverySchema::RESPONSE_SNIPPET => $this->resource->{WebhookDeliverySchema::RESPONSE_SNIPPET},
            WebhookDeliverySchema::ERROR_CATEGORY => $this->resource->{WebhookDeliverySchema::ERROR_CATEGORY},
            WebhookDeliverySchema::DURATION_MS => $this->resource->{WebhookDeliverySchema::DURATION_MS},
            WebhookDeliverySchema::DELIVERED_AT => $this->resource->{WebhookDeliverySchema::DELIVERED_AT},
            WebhookDeliverySchema::CREATED_AT => $this->resource->{WebhookDeliverySchema::CREATED_AT},
        ];
    }
}
