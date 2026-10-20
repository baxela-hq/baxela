<?php

namespace Modules\Tax\Http\Requests\Admin\TaxRate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Rules\CountryCodeRule;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

class TaxRateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // null on create, the rate id on update — /api/v1/tax/admin/tax-rates/{id}
        $id = $this->route('id');

        return [
            TaxRateSchema::NAME => ['required', 'string', 'max:255'],
            TaxRateSchema::COUNTRY_CODE => [
                'required',
                'string',
                'size:2',
                new CountryCodeRule,
                // one rate per country × class
                Rule::unique(TaxRateSchema::TABLE, TaxRateSchema::COUNTRY_CODE)
                    ->where(TaxRateSchema::TAX_CLASS_ID, $this->input(TaxRateSchema::TAX_CLASS_ID))
                    ->ignore($id),
            ],
            TaxRateSchema::TAX_CLASS_ID => [
                'required',
                'integer',
                Rule::exists(TaxClassSchema::TABLE, TaxClassSchema::ID),
            ],
            // percentage, 0–100, at most 4 fraction digits so the stored
            // decimal(7,4) never has to round silently
            TaxRateSchema::RATE => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'regex:/^\d{1,3}(\.\d{1,4})?$/',
            ],
            TaxRateSchema::IS_ACTIVE => ['required', 'boolean'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
