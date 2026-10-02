<?php

namespace Modules\Catalog\Http\Requests\Admin\DataTransfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStrategyEnum;

class ImportCatalogRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Fixed payload shape (no `sometimes`): defaults are merged so
        // the action always sees all three keys.
        $this->merge([
            CatalogImportSchema::REQ_ON_DUPLICATE => $this->input(CatalogImportSchema::REQ_ON_DUPLICATE)
                ?? CatalogImportStrategyEnum::UPDATE->value,
            CatalogImportSchema::REQ_DRY_RUN => (bool) $this->input(CatalogImportSchema::REQ_DRY_RUN, false),
        ]);
    }

    public function rules(): array
    {
        return [
            CatalogImportSchema::REQ_MEDIA_ID => ['required', 'integer'],
            CatalogImportSchema::REQ_ON_DUPLICATE => ['required', Rule::in([
                CatalogImportStrategyEnum::UPDATE->value,
                CatalogImportStrategyEnum::SKIP->value,
            ])],
            CatalogImportSchema::REQ_DRY_RUN => ['required', 'boolean'],
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
