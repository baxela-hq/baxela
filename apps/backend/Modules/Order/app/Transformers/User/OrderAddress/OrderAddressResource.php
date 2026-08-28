<?php

namespace Modules\Order\Transformers\User\OrderAddress;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;

class OrderAddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            OrderAddressSchema::ORDER_ID => $this->resource->{OrderAddressSchema::ORDER_ID},
            OrderAddressSchema::TYPE => $this->resource->{OrderAddressSchema::TYPE},
            OrderAddressSchema::FULL_NAME => $this->resource->{OrderAddressSchema::FULL_NAME},
            OrderAddressSchema::PHONE => $this->resource->{OrderAddressSchema::PHONE},
            OrderAddressSchema::ADDRESS_LINE => $this->resource->{OrderAddressSchema::ADDRESS_LINE},
            OrderAddressSchema::CITY => $this->resource->{OrderAddressSchema::CITY},
            OrderAddressSchema::POSTAL_CODE => $this->resource->{OrderAddressSchema::POSTAL_CODE},

        ];
    }
}
