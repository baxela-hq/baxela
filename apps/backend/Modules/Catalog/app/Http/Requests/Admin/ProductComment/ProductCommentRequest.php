<?php

namespace Modules\Catalog\Http\Requests\Admin\ProductComment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Catalog\Schemas\Product\ProductSchema as PSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;

class ProductCommentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // null on create, the comment id on update — /api/v1/catalog/admin/product-comments/{id}
        $id = $this->route('id');

        return [
            Schema::PRODUCT_ID => ['required', 'integer', Rule::exists(PSchema::TABLE, PSchema::ID)],
            // admin comments are replies; on update the parent may be cleared back to top-level
            Schema::PARENT_ID => [
                is_null($id) ? 'required' : 'nullable',
                'integer',
                Rule::exists(Schema::TABLE, Schema::ID),
            ],
            Schema::BODY => ['required', 'string', 'max:2000'],
            // status is forced to approved on create, so it is only meaningful on update
            Schema::STATUS => [
                is_null($id) ? 'nullable' : 'required',
                'string',
                new Enum(ProductCommentStatusEnum::class),
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
