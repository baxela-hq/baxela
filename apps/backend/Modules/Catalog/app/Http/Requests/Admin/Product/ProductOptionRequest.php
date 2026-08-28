<?php

namespace Modules\Catalog\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Catalog\Schemas\Product\ProductSchema;

class ProductOptionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            ProductSchema::RES_OPTIONS => ['required', 'array', 'min:1'],

            ProductSchema::RES_OPTIONS.'.*.option_id' => ['required', 'integer'],
            ProductSchema::RES_OPTIONS.'.*.value_ids' => ['required', 'array', 'min:1'],
            ProductSchema::RES_OPTIONS.'.*.value_ids.*' => ['required', 'integer'],
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
