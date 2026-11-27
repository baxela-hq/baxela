<?php

namespace Modules\Content\Http\Requests\Admin\Featured;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema as FISchema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Core\Rules\ScopedExists;

class UpdateFeaturedRequest extends FormRequest
{
    /**
     * Full-sync payload: the list must always be sent, array order
     * encodes the position.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            FISchema::REQ_POST_IDS => ['present', 'array', 'max:100'],
            FISchema::REQ_POST_IDS.'.*' => ['integer', 'distinct',
                new ScopedExists(PostSchema::TABLE, PostSchema::ID)],
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
