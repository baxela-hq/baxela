<?php

namespace Modules\Core\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;

class TransferPreviewRequest extends FormRequest
{
    /** @return array<string, array<int, string|array<int, string>|Enum>> */
    public function rules(): array
    {
        return [
            TransferImportSchema::REQ_FILE => [
                'required',
                'file',
                'max:'.(int) config('core.transfer.max_upload_kb'),
                'extensions:json',
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
