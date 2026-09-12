<?php

namespace Modules\Inventory\Http\Requests\Admin\InventoryStock;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

class InventoryStockRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            InventoryStockSchema::VARIANT_ID => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(CatalogGatewayInterface::class)->variantExists((int) $value)) {
                    $fail('The selected '.$attribute.' is invalid.');
                }
            }],
            InventoryStockSchema::QUANTITY => ['required', 'numeric', 'min:1', 'max:100000'],
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
