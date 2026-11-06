<?php

namespace Modules\Transfer\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Transfer\Schemas\TransferImport\TransferImportSchema;

class TransferImportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Fixed payload shape (no `sometimes`): defaults are merged so
        // the action always sees both keys. The import is multipart, so
        // booleans arrive as strings — `(bool) 'false'` would be true.
        $dryRun = $this->input(TransferImportSchema::REQ_DRY_RUN, false);

        $this->merge([
            TransferImportSchema::REQ_ON_DUPLICATE => $this->input(TransferImportSchema::REQ_ON_DUPLICATE)
                ?? TransferStrategyEnum::UPDATE->value,
            TransferImportSchema::REQ_DRY_RUN => is_bool($dryRun)
                ? $dryRun
                : filter_var($dryRun, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            TransferImportSchema::REQ_FILE => [
                'required',
                'file',
                'max:'.(int) config('transfer.max_upload_kb'),
                'extensions:json',
            ],
            TransferImportSchema::REQ_ON_DUPLICATE => ['required', Rule::in([
                TransferStrategyEnum::UPDATE->value,
                TransferStrategyEnum::SKIP->value,
            ])],
            TransferImportSchema::REQ_DRY_RUN => ['required', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
