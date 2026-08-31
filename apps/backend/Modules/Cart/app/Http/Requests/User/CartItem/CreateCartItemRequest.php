<?php

namespace Modules\Cart\Http\Requests\User\CartItem;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;

class CreateCartItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            CartItemSchema::VARIANT_ID => ['required', 'integer', 'exists:'.VariantSchema::TABLE.','.VariantSchema::ID],
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
