<?php

namespace Modules\User\Http\Requests\User\Address;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Core\Rules\CountryCodeRule;
use Modules\Core\Rules\RegionCodeRule;
use Modules\User\Rules\VatIdRule;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Schemas\Address\AddressTypeEnum;

class AddressRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            AddressSchema::TYPE => ['required', new Enum(AddressTypeEnum::class)],
            AddressSchema::FULL_NAME => ['required', 'string', 'max:255'],
            AddressSchema::PHONE => ['required', 'string', 'max:255'],
            AddressSchema::ADDRESS_LINE => ['required', 'string', 'max:255'],
            AddressSchema::CITY => ['required', 'string', 'max:255'],
            AddressSchema::POSTAL_CODE => ['required', 'string', 'max:255'],
            AddressSchema::COUNTRY_CODE => ['required', 'string', 'size:2', new CountryCodeRule],
            // Pair-validated against the submitted country (gap-C1a D4);
            // stored addresses are never re-validated.
            AddressSchema::REGION_CODE => [
                'nullable',
                'string',
                'max:10',
                new RegionCodeRule($this->input(AddressSchema::COUNTRY_CODE) ?? ''),
            ],
            AddressSchema::IS_DEFAULT => ['required', 'boolean'],
            // Business VAT id (gap-C1c): optional, offline format check
            // only; countries without a rule store it unvalidated.
            AddressSchema::VAT_ID => [
                'nullable',
                'string',
                'max:32',
                new VatIdRule($this->input(AddressSchema::COUNTRY_CODE) ?? ''),
            ],
        ];
    }

    /**
     * VAT ids store in normalized form (uppercased, separators
     * stripped) — the same form the format check runs against.
     */
    protected function prepareForValidation(): void
    {
        $vat = $this->input(AddressSchema::VAT_ID);

        if (is_string($vat) && trim($vat) !== '') {
            $this->merge([
                AddressSchema::VAT_ID => strtoupper(preg_replace('/[\s-]/', '', trim($vat))),
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
