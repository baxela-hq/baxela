<?php

namespace Modules\Catalog\Http\Requests\User\ProductComment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;

class ProductCommentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            Schema::BODY => ['required', 'string', 'max:2000'],
            Schema::PARENT_ID => ['nullable', 'integer', Rule::exists(Schema::TABLE, Schema::ID)],
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
