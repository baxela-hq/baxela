<?php

namespace Modules\Shipping\Transformers\Admin\Rate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;
use Modules\Shipping\Transformers\Admin\Method\MethodTranslationResource;

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
            RateSchema::RES_METHOD => $this->whenLoaded(RateSchema::RES_METHOD, function () {
                return [
                    MethodSchema::ID => $this->resource->{RateSchema::RES_METHOD}->{MethodSchema::ID},
                    MethodSchema::CODE => $this->resource->{RateSchema::RES_METHOD}->{MethodSchema::CODE},
                    MethodSchema::RES_TRANSLATIONS => MethodTranslationResource::collection(
                        $this->resource->{RateSchema::RES_METHOD}->{MethodSchema::RES_TRANSLATIONS}
                    ),
                ];
            }),
            RateSchema::RES_ZONE => $this->whenLoaded(RateSchema::RES_ZONE, function () {
                return [
                    ZoneSchema::ID => $this->resource->{RateSchema::RES_ZONE}->{ZoneSchema::ID},
                    ZoneSchema::NAME => $this->resource->{RateSchema::RES_ZONE}->{ZoneSchema::NAME},
                ];
            }),
        ];
    }
}
