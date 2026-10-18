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
            // top-level reviews carry the star rating; replies never do
            Schema::RATING => [
                Rule::prohibitedIf(fn (): bool => filled($this->input(Schema::PARENT_ID))),
                Rule::requiredIf(fn (): bool => blank($this->input(Schema::PARENT_ID))),
                'nullable',
                'integer',
                'between:1,5',
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
