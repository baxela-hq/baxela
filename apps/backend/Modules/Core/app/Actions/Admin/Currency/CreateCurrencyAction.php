<?php

namespace Modules\Core\Actions\Admin\Currency;

use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;

class CreateCurrencyAction
{
    public function handle(array $data): Currency
    {
        return Currency::query()->create([
            CurrencySchema::CODE => strtoupper($data[CurrencySchema::CODE]),
            CurrencySchema::NAME => $data[CurrencySchema::NAME],
            CurrencySchema::NATIVE_NAME => $data[CurrencySchema::NATIVE_NAME] ?? null,
            CurrencySchema::DECIMAL_PLACES => (int) $data[CurrencySchema::DECIMAL_PLACES],
            CurrencySchema::SYMBOL => $data[CurrencySchema::SYMBOL] ?? null,
            CurrencySchema::IS_SYMBOL_RIGHT => (bool) ($data[CurrencySchema::IS_SYMBOL_RIGHT] ?? false),
            CurrencySchema::EXCHANGE_RATE => $data[CurrencySchema::EXCHANGE_RATE] ?? '1.00000000',
            CurrencySchema::IS_ACTIVE => (bool) ($data[CurrencySchema::IS_ACTIVE] ?? true),
        ]);
    }
}
