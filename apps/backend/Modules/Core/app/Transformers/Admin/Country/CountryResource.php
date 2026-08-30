<?php

namespace Modules\Core\Transformers\Admin\Country;

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
            Schema::CODE3 => $this->{Schema::CODE3},
            Schema::NAME => $this->{Schema::NAME},
            Schema::NATIVE_NAME => $this->{Schema::NATIVE_NAME},
            Schema::EMOJI => $this->{Schema::EMOJI},
        ];
    }
}
