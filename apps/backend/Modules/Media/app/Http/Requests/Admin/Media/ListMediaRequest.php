<?php

namespace Modules\Media\Http\Requests\Admin\Media;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Media\Schemas\Media\MediaSchema;

class ListMediaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            MediaSchema::FOLDER_ID => ['required', 'numeric'],
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
