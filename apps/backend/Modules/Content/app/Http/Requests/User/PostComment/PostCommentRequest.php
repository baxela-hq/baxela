<?php

namespace Modules\Content\Http\Requests\User\PostComment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;

class PostCommentRequest extends FormRequest
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
