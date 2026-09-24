<?php

namespace Modules\Payment\Http\Requests\Admin\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;

class PaymentMethodRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            PaymentMethodSchema::IS_ACTIVE => ['required', 'boolean'],
            PaymentMethodSchema::SORT_ORDER => ['required', 'integer', 'min:0'],
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
