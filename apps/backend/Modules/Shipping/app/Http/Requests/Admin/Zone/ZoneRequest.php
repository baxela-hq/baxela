<?php

namespace Modules\Shipping\Http\Requests\Admin\Zone;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Rules\CountryCodeRule;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

class ZoneRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            ZoneSchema::NAME => ['required', 'string', 'max:255'],
            ZoneSchema::IS_ACTIVE => ['required', 'boolean'],
            ZoneSchema::POSITION => ['nullable', 'numeric', 'max:255'],

            ZoneSchema::COUNTRY_CODES => ['nullable', 'array'],
            ZoneSchema::COUNTRY_CODES.'.*' => ['string', 'size:2', new CountryCodeRule],
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
