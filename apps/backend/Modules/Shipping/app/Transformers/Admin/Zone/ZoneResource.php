<?php

namespace Modules\Shipping\Transformers\Admin\Zone;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Schemas\Country\CountrySchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

class ZoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            ZoneSchema::ID => $this->{ZoneSchema::ID},
            ZoneSchema::NAME => $this->{ZoneSchema::NAME},
            ZoneSchema::IS_ACTIVE => $this->{ZoneSchema::IS_ACTIVE},
            ZoneSchema::POSITION => $this->{ZoneSchema::POSITION},
            ZoneSchema::CREATED_AT => $this->{ZoneSchema::CREATED_AT},
            ZoneSchema::UPDATED_AT => $this->{ZoneSchema::UPDATED_AT},
            ZoneSchema::COUNTRY_CODES => $this->whenLoaded(ZoneSchema::RES_COUNTRIES,
                fn () => $this->resource->countries->pluck(CountrySchema::CODE)->all()),
        ];
    }
}
