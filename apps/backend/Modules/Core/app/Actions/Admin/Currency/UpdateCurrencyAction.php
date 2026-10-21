<?php

namespace Modules\Core\Actions\Admin\Currency;

use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;

class UpdateCurrencyAction
{
    public function handle(string $id, array $data): Currency
    {
        $currency = Currency::query()->findOrFail($id);

        $attributes = array_intersect_key($data, array_flip([
            CurrencySchema::CODE,
            CurrencySchema::NAME,
            CurrencySchema::NATIVE_NAME,
            CurrencySchema::DECIMAL_PLACES,
            CurrencySchema::SYMBOL,
            CurrencySchema::IS_SYMBOL_RIGHT,
            CurrencySchema::EXCHANGE_RATE,
            CurrencySchema::IS_ACTIVE,
        ]));

        // The base currency defines the rate scale — it is always 1 and
        // stays active. (Default switching is owned by the Setting flow.)
        if ($currency->{CurrencySchema::IS_DEFAULT}) {
            unset($attributes[CurrencySchema::IS_ACTIVE]);
            $attributes[CurrencySchema::EXCHANGE_RATE] = '1.00000000';
        }

        $currency->update($attributes);

        return $currency->refresh();
    }
}
