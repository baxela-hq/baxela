<?php

namespace Modules\Inventory\Http\Requests\Admin\InventoryStock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

class InventoryStockRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            InventoryStockSchema::VARIANT_ID => ['required', Rule::exists(VariantSchema::TABLE)],
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
