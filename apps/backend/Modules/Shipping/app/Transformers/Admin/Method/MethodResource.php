<?php

namespace Modules\Shipping\Transformers\Admin\Method;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shipping\Schemas\Method\MethodSchema;

class MethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            MethodSchema::ID => $this->{MethodSchema::ID},
            MethodSchema::CODE => $this->{MethodSchema::CODE},
            MethodSchema::IS_ACTIVE => $this->{MethodSchema::IS_ACTIVE},
            MethodSchema::POSITION => $this->{MethodSchema::POSITION},
            MethodSchema::CREATED_AT => $this->{MethodSchema::CREATED_AT},
            MethodSchema::UPDATED_AT => $this->{MethodSchema::UPDATED_AT},
            MethodSchema::RES_TRANSLATIONS => MethodTranslationResource::collection($this->whenLoaded(MethodSchema::RES_TRANSLATIONS)),
        ];
    }
}
