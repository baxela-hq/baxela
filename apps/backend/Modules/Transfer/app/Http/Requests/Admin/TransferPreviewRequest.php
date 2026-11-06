<?php

namespace Modules\Transfer\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Transfer\Schemas\TransferImport\TransferImportSchema;

class TransferPreviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            TransferImportSchema::REQ_FILE => [
                'required',
                'file',
                'max:'.(int) config('transfer.max_upload_kb'),
                'extensions:json',
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
