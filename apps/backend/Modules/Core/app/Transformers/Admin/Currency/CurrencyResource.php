<?php

namespace Modules\Core\Transformers\Admin\Currency;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Schemas\Currency\CurrencySchema as Schema;

class CurrencyResource extends JsonResource
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
            Schema::NATIVE_NAME => $this->{Schema::NATIVE_NAME},
            Schema::DECIMAL_PLACES => $this->{Schema::DECIMAL_PLACES},
            Schema::SYMBOL => $this->{Schema::SYMBOL},
            Schema::IS_DEFAULT => $this->{Schema::IS_DEFAULT},
            Schema::IS_SYMBOL_RIGHT => $this->{Schema::IS_SYMBOL_RIGHT},
            Schema::CREATED_AT => $this->{Schema::CREATED_AT},
            Schema::UPDATED_AT => $this->{Schema::UPDATED_AT},
        ];
    }
}
