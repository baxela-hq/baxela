<?php

namespace Modules\Catalog\Http\Requests\Admin\DataTransfer;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;

class PreviewCatalogImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Existence and json-ness are resolved through the media
            // gateway by the action — Catalog never reads media tables.
            CatalogImportSchema::REQ_MEDIA_ID => ['required', 'integer'],
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
