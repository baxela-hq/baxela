<?php

namespace Modules\Tax\Transformers\Admin\TaxRate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

class TaxRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            TaxRateSchema::ID => $this->{TaxRateSchema::ID},
            TaxRateSchema::NAME => $this->{TaxRateSchema::NAME},
            TaxRateSchema::COUNTRY_CODE => $this->{TaxRateSchema::COUNTRY_CODE},
            TaxRateSchema::TAX_CLASS_ID => $this->{TaxRateSchema::TAX_CLASS_ID},
            // storage-format decimal string ('8.5000') — the exact rate,
            // never a float
            TaxRateSchema::RATE => $this->{TaxRateSchema::RATE},
            TaxRateSchema::IS_ACTIVE => $this->{TaxRateSchema::IS_ACTIVE},
            TaxRateSchema::CREATED_AT => $this->{TaxRateSchema::CREATED_AT},
            TaxRateSchema::UPDATED_AT => $this->{TaxRateSchema::UPDATED_AT},
            TaxRateSchema::RES_TAX_CLASS => $this->whenLoaded(TaxRateSchema::RELATION_TAX_CLASS,
                fn ($taxClass) => [
                    TaxClassSchema::ID => $taxClass->{TaxClassSchema::ID},
                    TaxClassSchema::NAME => $taxClass->{TaxClassSchema::NAME},
                ]),
        ];
    }
}
