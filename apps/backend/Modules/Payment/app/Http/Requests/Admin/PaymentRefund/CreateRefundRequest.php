<?php

namespace Modules\Payment\Http\Requests\Admin\PaymentRefund;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;

class CreateRefundRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // platform money convention: decimal(12,2) — two fraction digits
            PaymentRefundSchema::AMOUNT => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            PaymentRefundSchema::REASON => ['required', 'string', 'max:1000'],
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
