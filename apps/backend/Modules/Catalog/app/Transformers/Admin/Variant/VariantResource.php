<?php

namespace Modules\Catalog\Transformers\Admin\Variant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Variant\VariantSchema;

class VariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {

        return [
            VariantSchema::ID => $this->resource->{VariantSchema::ID},
            VariantSchema::SKU => $this->resource->{VariantSchema::SKU},
            VariantSchema::BARCODE => $this->resource->{VariantSchema::BARCODE},
            VariantSchema::PRICE => $this->resource->{VariantSchema::PRICE},
            VariantSchema::QUANTITY => $this->resource->{VariantSchema::QUANTITY},
            VariantSchema::IS_DEFAULT => $this->resource->{VariantSchema::IS_DEFAULT},
            VariantSchema::RES_OPTION_VALUE_IDS => $this->whenLoaded(
                VariantSchema::RES_OPTION_VALUES,
                fn () => $this->resource->{VariantSchema::RES_OPTION_VALUES}->pluck('id')
            ),
            VariantSchema::RES_OPTION_VALUES => $this->whenLoaded(
                VariantSchema::RES_OPTION_VALUES,
            ),
        ];
    }
}
