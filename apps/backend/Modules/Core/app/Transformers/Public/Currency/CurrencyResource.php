<?php

namespace Modules\Core\Transformers\Public\Currency;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Contracts\Gateways\Core\DTOs\CurrencyDto;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CurrencyDto $currency */
        $currency = $this->resource;

        return [
            'code' => $currency->code,
            'name' => $currency->name,
            'native_name' => $currency->native_name,
            'symbol' => $currency->symbol,
            'is_default' => $currency->is_default,
            'is_symbol_right' => $currency->is_symbol_right,
            'decimal_places' => (int) $currency->decimal_places,
        ];
    }
}
