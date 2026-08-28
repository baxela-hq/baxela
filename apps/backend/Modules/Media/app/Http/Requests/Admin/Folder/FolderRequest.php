<?php

namespace Modules\Media\Http\Requests\Admin\Folder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Rules\UniquePair;
use Modules\Media\Schemas\Folder\FolderSchema;

class FolderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            FolderSchema::PARENT_ID => ['nullable', 'numeric', Rule::exists(FolderSchema::TABLE, FolderSchema::ID)],
            FolderSchema::NAME => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}](?:[\p{L}\p{N} _.\-()]*[\p{L}\p{N}_\-()])?$/u',
                new UniquePair(FolderSchema::TABLE, [
                    FolderSchema::NAME => $this->input(FolderSchema::NAME),
                    FolderSchema::PARENT_ID => $this->input(FolderSchema::PARENT_ID),
                ], $id ? (int) $id : null),
            ],
            FolderSchema::POSITION => ['nullable', 'integer', 'max:255'],
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
