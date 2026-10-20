<?php

namespace Modules\Tax\Transformers\Admin\TaxClass;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;

class TaxClassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            TaxClassSchema::ID => $this->{TaxClassSchema::ID},
            TaxClassSchema::NAME => $this->{TaxClassSchema::NAME},
            TaxClassSchema::IS_DEFAULT => $this->{TaxClassSchema::IS_DEFAULT},
            TaxClassSchema::IS_SHIPPING => $this->{TaxClassSchema::IS_SHIPPING},
            TaxClassSchema::CREATED_AT => $this->{TaxClassSchema::CREATED_AT},
            TaxClassSchema::UPDATED_AT => $this->{TaxClassSchema::UPDATED_AT},
        ];
    }
}
