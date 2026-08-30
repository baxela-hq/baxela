<?php

namespace Modules\User\Transformers\User\Address;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\User\Schemas\Address\AddressSchema;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            AddressSchema::ID => $this->resource->{AddressSchema::ID},
            AddressSchema::TYPE => $this->resource->{AddressSchema::TYPE},
            AddressSchema::FULL_NAME => $this->resource->{AddressSchema::FULL_NAME},
            AddressSchema::PHONE => $this->resource->{AddressSchema::PHONE},
            AddressSchema::ADDRESS_LINE => $this->resource->{AddressSchema::ADDRESS_LINE},
            AddressSchema::CITY => $this->resource->{AddressSchema::CITY},
            AddressSchema::POSTAL_CODE => $this->resource->{AddressSchema::POSTAL_CODE},
            AddressSchema::COUNTRY_CODE => $this->resource->{AddressSchema::COUNTRY_CODE},
            AddressSchema::IS_DEFAULT => $this->resource->{AddressSchema::IS_DEFAULT},
            AddressSchema::CREATED_AT => $this->resource->{AddressSchema::CREATED_AT},
            AddressSchema::UPDATED_AT => $this->resource->{AddressSchema::UPDATED_AT},
        ];
    }
}
