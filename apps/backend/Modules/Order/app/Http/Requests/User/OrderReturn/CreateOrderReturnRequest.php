<?php

namespace Modules\Order\Http\Requests\User\OrderReturn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Order\Schemas\OrderReturn\OrderReturnReasonEnum;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;

class CreateOrderReturnRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            OrderReturnSchema::REASON => ['required', 'string', Rule::in(array_column(OrderReturnReasonEnum::cases(), 'value'))],
            OrderReturnSchema::NOTE => ['nullable', 'string', 'max:1000'],
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
