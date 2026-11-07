<?php

namespace Modules\Webhooks\Transformers\Admin\WebhookEndpoint;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Webhooks\Models\WebhookEndpoint;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;

/**
 * The secret is NEVER exposed after creation — it is shown once by the
 * create/regenerate responses' `secret` key (plain, not this resource).
 *
 * @mixin WebhookEndpoint
 */
class WebhookEndpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            WebhookEndpointSchema::ID => $this->resource->getKey(),
            WebhookEndpointSchema::NAME => $this->resource->{WebhookEndpointSchema::NAME},
            WebhookEndpointSchema::URL => $this->resource->{WebhookEndpointSchema::URL},
            WebhookEndpointSchema::EVENTS => $this->resource->{WebhookEndpointSchema::EVENTS},
            WebhookEndpointSchema::IS_ACTIVE => $this->resource->{WebhookEndpointSchema::IS_ACTIVE},
            WebhookEndpointSchema::IS_ARCHIVED => $this->resource->{WebhookEndpointSchema::IS_ARCHIVED},
            WebhookEndpointSchema::CONSECUTIVE_FAILURES => $this->resource->{WebhookEndpointSchema::CONSECUTIVE_FAILURES},
            WebhookEndpointSchema::CREATED_AT => $this->resource->{WebhookEndpointSchema::CREATED_AT},
            WebhookEndpointSchema::UPDATED_AT => $this->resource->{WebhookEndpointSchema::UPDATED_AT},
        ];
    }
}
