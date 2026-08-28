<?php

namespace Modules\Catalog\Transformers\Admin\Option;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Transformers\Admin\OptionValue\OptionValueResource;

class OptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            OptionSchema::ID => $this->resource->{OptionSchema::ID},
            OptionSchema::POSITION => $this->resource->{OptionSchema::POSITION},
            OptionSchema::CREATED_AT => $this->resource->{OptionSchema::CREATED_AT},
            OptionSchema::UPDATED_AT => $this->resource->{OptionSchema::UPDATED_AT},
            OptionSchema::RES_TRANSLATIONS => OptionTranslationResource::collection($this->whenLoaded(OptionSchema::RES_TRANSLATIONS)),
            OptionSchema::RES_VALUES => OptionValueResource::collection($this->whenLoaded(OptionSchema::RES_VALUES)),
            OptionSchema::RES_VALUES_COUNT => $this->resource->{OptionSchema::RES_VALUES_COUNT},
        ];
    }
}
