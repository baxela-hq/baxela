<?php

namespace Modules\Order\Transformers\User\OrderReturn;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;

class OrderReturnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            OrderReturnSchema::ID => $this->{OrderReturnSchema::ID},
            OrderReturnSchema::ORDER_ID => $this->{OrderReturnSchema::ORDER_ID},
            OrderSchema::ORDER_CODE => $this->whenLoaded(OrderReturnSchema::RES_ORDER,
                fn () => $this->resource->{OrderReturnSchema::RES_ORDER}->{OrderSchema::ORDER_CODE}),
            OrderReturnSchema::STATUS => $this->{OrderReturnSchema::STATUS},
            OrderReturnSchema::REASON => $this->{OrderReturnSchema::REASON},
            OrderReturnSchema::NOTE => $this->{OrderReturnSchema::NOTE},
            OrderReturnSchema::TRACKING_NUMBER => $this->{OrderReturnSchema::TRACKING_NUMBER},
            OrderReturnSchema::CREATED_AT => $this->{OrderReturnSchema::CREATED_AT},
            OrderReturnSchema::UPDATED_AT => $this->{OrderReturnSchema::UPDATED_AT},
        ];
    }
}
