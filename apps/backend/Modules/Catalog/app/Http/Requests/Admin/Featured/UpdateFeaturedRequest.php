<?php

namespace Modules\Catalog\Http\Requests\Admin\Featured;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema as FISchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Core\Rules\ScopedExists;

class UpdateFeaturedRequest extends FormRequest
{
    /**
     * Full-sync payload: every list must be sent, array order encodes
     * the position within each section.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            FISchema::REQ_PRODUCT_IDS => ['present', 'array', 'max:100'],
            FISchema::REQ_PRODUCT_IDS.'.*' => ['integer', 'distinct',
                new ScopedExists(ProductSchema::TABLE, ProductSchema::ID, [ProductSchema::DELETED_AT => null])],

            FISchema::REQ_CATEGORY_IDS => ['present', 'array', 'max:100'],
            FISchema::REQ_CATEGORY_IDS.'.*' => ['integer', 'distinct',
                new ScopedExists(CategorySchema::TABLE, CategorySchema::ID)],

            FISchema::REQ_BRAND_IDS => ['present', 'array', 'max:100'],
            FISchema::REQ_BRAND_IDS.'.*' => ['integer', 'distinct',
                new ScopedExists(BrandSchema::TABLE, BrandSchema::ID)],
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
