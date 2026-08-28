<?php

namespace Modules\Media\Http\Requests\Admin\Media;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Media\Schemas\Media\MediaSchema;

class UpdateMediaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            MediaSchema::NAME => ['nullable', 'string', 'max:255', 'regex:/^[\p{L}\p{N}](?:[\p{L}\p{N} _.\-()]*[\p{L}\p{N}_\-()])?$/u'],
            MediaSchema::FOLDER_ID => ['nullable', 'numeric'],
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
