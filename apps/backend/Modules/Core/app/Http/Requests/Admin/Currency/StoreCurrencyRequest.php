<?php

namespace Modules\Core\Http\Requests\Admin\Currency;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Schemas\Currency\CurrencySchema;

class StoreCurrencyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            CurrencySchema::CODE => [
                'required', 'string', 'size:3',
                Rule::unique(CurrencySchema::TABLE, CurrencySchema::CODE),
            ],
            CurrencySchema::NAME => ['required', 'string', 'max:100'],
            CurrencySchema::NATIVE_NAME => ['nullable', 'string', 'max:100'],
            CurrencySchema::DECIMAL_PLACES => ['required', 'integer', 'between:0,8'],
            CurrencySchema::SYMBOL => ['nullable', 'string', 'max:10'],
            CurrencySchema::IS_SYMBOL_RIGHT => ['boolean'],
            // Unsignaled decimal string, 1–8 fraction digits, non-zero —
            // matches Money::convert's rate contract and decimal(16,8) storage.
            CurrencySchema::EXCHANGE_RATE => ['required', 'regex:/^\d+\.\d{1,8}$/', 'not_in:0'],
            CurrencySchema::IS_ACTIVE => ['boolean'],
        ];
    }
}
