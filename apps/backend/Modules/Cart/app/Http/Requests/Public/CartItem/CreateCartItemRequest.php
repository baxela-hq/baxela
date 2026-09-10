<?php

namespace Modules\Cart\Http\Requests\Public\CartItem;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;

class CreateCartItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Referenceability is checked through the Catalog gateway so
            // this module never touches Catalog's tables directly.
            CartItemSchema::VARIANT_ID => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! app(CatalogGatewayInterface::class)->variantExists((int) $value)) {
                        $fail('The variant does not exist.');
                    }
                },
            ],
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
