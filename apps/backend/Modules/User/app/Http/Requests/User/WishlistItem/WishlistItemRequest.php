<?php

namespace Modules\User\Http\Requests\User\WishlistItem;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\User\Schemas\WishlistItem\WishlistItemSchema;

class WishlistItemRequest extends FormRequest
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
            WishlistItemSchema::PRODUCT_ID => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! app(CatalogGatewayInterface::class)->productExists((int) $value)) {
                        $fail('The product does not exist.');
                    }
                },
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
