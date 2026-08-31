<?php

namespace Modules\Payment\Http\Requests\Admin\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;

class UpdatePaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            PaymentSchema::STATUS => [
                'required',
                new Enum(PaymentStatusEnum::class),
                'in:'.implode(',', [
                    PaymentStatusEnum::SUCCESS->value,
                    PaymentStatusEnum::FAILED->value,
                ]),
            ],
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
