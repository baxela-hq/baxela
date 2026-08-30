<?php

namespace Modules\Shipping\Transformers\Admin\Rate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shipping\Schemas\Rate\RateSchema;

class RateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            RateSchema::ID => $this->{RateSchema::ID},
            RateSchema::METHOD_ID => $this->{RateSchema::METHOD_ID},
            RateSchema::ZONE_ID => $this->{RateSchema::ZONE_ID},
            RateSchema::PRICE => $this->{RateSchema::PRICE},
            RateSchema::CREATED_AT => $this->{RateSchema::CREATED_AT},
            RateSchema::UPDATED_AT => $this->{RateSchema::UPDATED_AT},
        ];
    }
}
