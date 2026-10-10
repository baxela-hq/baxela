<?php

namespace Modules\Content\Http\Requests\Admin\Featured;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema as FISchema;
use Modules\Content\Schemas\Post\PostSchema;

class UpdateFeaturedRequest extends FormRequest
{
    /**
     * Full-sync payload: the list must always be sent, array order
     * encodes the position.
     */
    public function rules(): array
    {
        return [
            FISchema::REQ_POST_IDS => ['present', 'array', 'max:100'],
            FISchema::REQ_POST_IDS.'.*' => ['integer', 'distinct',
                Rule::exists(PostSchema::TABLE, PostSchema::ID)],
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
