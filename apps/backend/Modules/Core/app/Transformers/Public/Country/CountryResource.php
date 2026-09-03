<?php

namespace Modules\Core\Transformers\Public\Country;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Schemas\Country\CountrySchema as Schema;

class CountryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            Schema::ID => $this->{Schema::ID},
            Schema::CODE => $this->{Schema::CODE},
            Schema::NAME => $this->{Schema::NAME},
        ];
    }
}
