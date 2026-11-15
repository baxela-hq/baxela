<?php

namespace Modules\Media\Http\Requests\Admin\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Enum;
use Modules\Media\Schemas\Media\MediaSchema;

class ListMediaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    /** @return array<string, array<int, string|array<int, string>|Enum|UploadedFile|null>> */
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
