<?php

namespace Modules\Payment\Http\Requests\Admin\PaymentRefund;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;

class ResolveRefundRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // only the two operator-verified outcomes close an ambiguous row
            PaymentRefundSchema::STATUS => ['required', 'string', Rule::in(['success', 'failed'])],
            // the gateway refund id, when the operator confirms success
            PaymentRefundSchema::TRANSACTION_ID => [
                'nullable',
                'string',
                'max:255',
                'required_if:'.PaymentRefundSchema::STATUS.',success',
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
