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
        // the action always sees both keys.
        $this->merge([
            TransferImportSchema::REQ_ON_DUPLICATE => $this->input(TransferImportSchema::REQ_ON_DUPLICATE)
                ?? TransferStrategyEnum::UPDATE->value,
            TransferImportSchema::REQ_DRY_RUN => (bool) $this->input(TransferImportSchema::REQ_DRY_RUN, false),
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
