<?php

namespace Modules\Tax\Http\Requests\Admin\TaxRate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Rules\CountryCodeRule;
use Modules\Core\Rules\RegionCodeRule;
use Modules\Tax\Rules\ScopedRateUniqueRule;
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
            ],
            // '' = the country-wide scope; a real value is pair-validated
            // against the submitted country (gap-C1a D1/D4). One rate per
            // (country, region, class) — the scope-keyed unique.
            // '' (normalized from null below) = the country-wide scope;
            // the unique rule only fires on non-null values, hence the
            // normalization instead of a nullable rule (gap-C1a D1).
            // '' (normalized below) = the country-wide scope. The
            // scope-keyed uniqueness (country, region, class) lives in
            // ScopedRateUniqueRule — an IMPLICIT rule, because Laravel
            // skips ordinary rules for empty-string values and '' is a
            // real scope here, not an absence (gap-C1a D1).
            TaxRateSchema::REGION_CODE => [
                'max:10',
                new RegionCodeRule($this->input(TaxRateSchema::COUNTRY_CODE) ?? ''),
                new ScopedRateUniqueRule(
                    country: $this->input(TaxRateSchema::COUNTRY_CODE) ?? '',
                    taxClassId: (int) ($this->input(TaxRateSchema::TAX_CLASS_ID) ?? 0),
                    ignoreId: $id !== null ? (int) $id : null,
                ),
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
     * Normalize an omitted region to the country-wide scope ('') so the
     * unique rule fires on duplicates of the '' row.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            TaxRateSchema::REGION_CODE => strtoupper(trim((string) $this->input(TaxRateSchema::REGION_CODE))) ?: '',
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
