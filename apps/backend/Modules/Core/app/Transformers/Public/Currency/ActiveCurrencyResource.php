<?php

namespace Modules\Core\Transformers\Public\Currency;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema as Schema;

/**
 * The storefront currency switcher's payload: active currencies with their
 * display-conversion rates. The base (default) currency always carries the
 * rate it was priced in — 1.
 */
class ActiveCurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Currency $currency */
        $currency = $this->resource;

        return [
            Schema::CODE => $currency->{Schema::CODE},
            Schema::NAME => $currency->{Schema::NAME},
            Schema::NATIVE_NAME => $currency->{Schema::NATIVE_NAME},
            Schema::SYMBOL => $currency->{Schema::SYMBOL},
            Schema::IS_DEFAULT => $currency->{Schema::IS_DEFAULT},
            Schema::IS_SYMBOL_RIGHT => $currency->{Schema::IS_SYMBOL_RIGHT},
            Schema::DECIMAL_PLACES => (int) $currency->{Schema::DECIMAL_PLACES},
            Schema::EXCHANGE_RATE => $currency->{Schema::EXCHANGE_RATE},
        ];
    }
}
