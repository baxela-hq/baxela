<?php

namespace Modules\Order\Http\Requests\Admin\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;

class OrderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            OrderSchema::STATUS => ['required', new Enum(OrderStatusEnum::class)],
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
