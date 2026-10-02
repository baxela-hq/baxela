<?php

namespace Modules\Catalog\Http\Requests\Admin\Featured;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema as FISchema;
use Modules\Catalog\Schemas\Product\ProductSchema;

class UpdateFeaturedRequest extends FormRequest
{
    /**
     * Full-sync payload: every list must be sent, array order encodes
     * the position within each section.
     */
    public function rules(): array
    {
        return [
            FISchema::REQ_PRODUCT_IDS => ['present', 'array', 'max:100'],
            FISchema::REQ_PRODUCT_IDS.'.*' => ['integer', 'distinct',
                Rule::exists(ProductSchema::TABLE, ProductSchema::ID)
                    ->whereNull(ProductSchema::DELETED_AT)],

            FISchema::REQ_CATEGORY_IDS => ['present', 'array', 'max:100'],
            FISchema::REQ_CATEGORY_IDS.'.*' => ['integer', 'distinct',
                Rule::exists(CategorySchema::TABLE, CategorySchema::ID)],
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
