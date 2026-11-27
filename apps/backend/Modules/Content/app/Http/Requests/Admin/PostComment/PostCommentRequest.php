<?php

namespace Modules\Content\Http\Requests\Admin\PostComment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Content\Schemas\Post\PostSchema as PSchema;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;
use Modules\Core\Rules\ScopedExists;

class PostCommentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // null on create, the comment id on update — /api/v1/content/admin/post-comments/{id}
        $id = $this->route('id');

        return [
            Schema::POST_ID => ['required', 'integer', new ScopedExists(PSchema::TABLE, PSchema::ID)],
            // admin comments are replies; on update the parent may be cleared back to top-level
            Schema::PARENT_ID => [
                is_null($id) ? 'required' : 'nullable',
                'integer',
                new ScopedExists(Schema::TABLE, Schema::ID),
            ],
            Schema::BODY => ['required', 'string', 'max:2000'],
            // status is forced to approved on create, so it is only meaningful on update
            Schema::STATUS => [
                is_null($id) ? 'nullable' : 'required',
                'string',
                new Enum(PostCommentStatusEnum::class),
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
