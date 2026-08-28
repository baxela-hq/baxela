<?php

namespace Modules\Order\Http\Requests\User\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressTypeEnum;

class OrderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            OrderAddressSchema::TYPE => ['required', 'string', new Enum(OrderAddressTypeEnum::class)],
            OrderAddressSchema::FULL_NAME => ['required', 'string', 'min:3', 'max:255'],
            OrderAddressSchema::PHONE => ['required', 'string', 'max:255'],
            OrderAddressSchema::ADDRESS_LINE => ['required', 'string', 'max:255'],
            OrderAddressSchema::CITY => ['required', 'string', 'max:255'],
            OrderAddressSchema::POSTAL_CODE => ['required', 'string', 'max:255'],
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
