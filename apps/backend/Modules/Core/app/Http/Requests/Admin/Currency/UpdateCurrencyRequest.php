<?php

namespace Modules\Core\Http\Requests\Admin\Currency;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Schemas\Currency\CurrencySchema;

class UpdateCurrencyRequest extends FormRequest
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
                'sometimes', 'string', 'size:3',
                Rule::unique(CurrencySchema::TABLE, CurrencySchema::CODE)
                    ->ignore($this->route('id')),
            ],
            CurrencySchema::NAME => ['sometimes', 'string', 'max:100'],
            CurrencySchema::NATIVE_NAME => ['nullable', 'string', 'max:100'],
            CurrencySchema::DECIMAL_PLACES => ['sometimes', 'integer', 'between:0,8'],
            CurrencySchema::SYMBOL => ['nullable', 'string', 'max:10'],
            CurrencySchema::IS_SYMBOL_RIGHT => ['boolean'],
            CurrencySchema::EXCHANGE_RATE => ['sometimes', 'regex:/^\d+\.\d{1,8}$/', 'not_in:0'],
            CurrencySchema::IS_ACTIVE => ['boolean'],
        ];
    }
}
