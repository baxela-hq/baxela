<?php

namespace Modules\Catalog\Http\Requests\Admin\ProductComment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Catalog\Schemas\Product\ProductSchema as PSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Core\Rules\ScopedExists;

class ProductCommentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // null on create, the comment id on update — /api/v1/catalog/admin/product-comments/{id}
        $id = $this->route('id');

        return [
            Schema::PRODUCT_ID => ['required', 'integer', new ScopedExists(PSchema::TABLE, PSchema::ID)],
            // admin comments are replies; on update the parent may be cleared back to top-level
            Schema::PARENT_ID => [
                is_null($id) ? 'required' : 'nullable',
                'integer',
                new ScopedExists(Schema::TABLE, Schema::ID),
            ],
            Schema::BODY => ['required', 'string', 'max:2000'],
            // ratings belong to top-level reviews only; null keeps legacy text-only rows editable
            Schema::RATING => [
                Rule::prohibitedIf(fn (): bool => filled($this->input(Schema::PARENT_ID))),
                'nullable',
                'integer',
                'between:1,5',
            ],
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
