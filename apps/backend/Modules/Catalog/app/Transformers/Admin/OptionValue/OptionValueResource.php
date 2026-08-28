<?php

namespace Modules\Catalog\Transformers\Admin\OptionValue;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Transformers\Admin\OptionValue\OptionValueTranslationResource as OVTResource;

class OptionValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            OptionValueSchema::ID => $this->{OptionValueSchema::ID},
            OptionValueSchema::OPTION_ID => $this->{OptionValueSchema::OPTION_ID},
            OptionValueSchema::POSITION => $this->{OptionValueSchema::POSITION},
            OptionValueSchema::CREATED_AT => $this->{OptionValueSchema::CREATED_AT},
            OptionValueSchema::UPDATED_AT => $this->{OptionValueSchema::UPDATED_AT},
            OptionValueSchema::RES_TRANSLATIONS => OVTResource::collection($this->whenLoaded(OptionValueSchema::RES_TRANSLATIONS)),
        ];
    }
}
