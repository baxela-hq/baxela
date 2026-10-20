<?php

namespace Modules\Order\Http\Requests\Admin\OrderReturn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnStatusEnum;

class UpdateOrderReturnRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            OrderReturnSchema::STATUS => [
                'required',
                'string',
                Rule::in(array_column(OrderReturnStatusEnum::cases(), 'value')),
            ],
            OrderReturnSchema::ADMIN_NOTE => ['nullable', 'string', 'max:1000'],
            // only meaningful on the received transition: restock the
            // returned items (default true)
            OrderReturnSchema::REQ_RESTORE_STOCK => ['nullable', 'boolean'],
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
