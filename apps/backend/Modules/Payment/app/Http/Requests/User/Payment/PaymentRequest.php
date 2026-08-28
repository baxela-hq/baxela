<?php

namespace Modules\Payment\Http\Requests\User\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentSchema;

class PaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            PaymentSchema::ORDER_ID => ['required', 'string', 'numeric'],
            PaymentSchema::METHOD => ['required', 'string', new Enum(PaymentMethodEnum::class)],
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
