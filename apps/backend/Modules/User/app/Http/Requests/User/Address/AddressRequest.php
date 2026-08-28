<?php

namespace Modules\User\Http\Requests\User\Address;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
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
            AddressSchema::IS_DEFAULT => ['required', 'boolean'],
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
