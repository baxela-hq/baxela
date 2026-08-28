<?php

namespace Modules\Cart\Http\Requests\User\CartItem;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Cart\Schemas\CartItem\CartItemSchema;

class UpdateCartItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            CartItemSchema::QUANTITY => ['required', 'integer', 'min:1', 'max:10000'],
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
