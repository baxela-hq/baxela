<?php

namespace Modules\Notification\Transformers\User\PushSubscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;

class PushSubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array. Encryption keys are never
     * exposed; the endpoint identifies the registration.
     */
    public function toArray(Request $request): array
    {
        return [
            PushSubscriptionSchema::ID => $this->resource->{PushSubscriptionSchema::ID},
            PushSubscriptionSchema::ENDPOINT => $this->resource->{PushSubscriptionSchema::ENDPOINT},
            PushSubscriptionSchema::USER_AGENT => $this->resource->{PushSubscriptionSchema::USER_AGENT},
            PushSubscriptionSchema::LOCALE => $this->resource->{PushSubscriptionSchema::LOCALE},
            PushSubscriptionSchema::CREATED_AT => $this->resource->{PushSubscriptionSchema::CREATED_AT},
        ];
    }
}
