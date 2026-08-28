<?php

namespace Modules\Media\Http\Requests\Admin\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Modules\Media\DTOs\Admin\CreateMediaInput;
use Modules\Media\Schemas\Folder\FolderSchema;
use Modules\Media\Schemas\Media\MediaMimeTypeEnum;
use Modules\Media\Schemas\Media\MediaSchema;

class MediaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            MediaSchema::REQ_FILE => ['required',
                File::types(MediaMimeTypeEnum::values())
                    ->max('20mb'),
            ],
            MediaSchema::FOLDER_ID => ['nullable', 'numeric', Rule::exists(FolderSchema::TABLE, FolderSchema::ID)],
            MediaSchema::METADATA => ['nullable', 'array'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): CreateMediaInput
    {
        $dto = new CreateMediaInput;
        $dto->file = $this->{MediaSchema::REQ_FILE};
        $dto->folder_id = $this->{MediaSchema::FOLDER_ID};

        return $dto;
    }
}
