<?php

namespace Modules\Catalog\Http\Requests\User\ProductComment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema as RSchema;

class ReactToProductCommentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            RSchema::REACTION => ['required', 'string', new Enum(ProductCommentReactionEnum::class)],
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
