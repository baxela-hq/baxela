<?php

namespace Modules\Catalog\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Catalog\Schemas\ProductImport\ProductImportSchema;

class PreviewImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Existence and csv-ness are resolved through the media
            // gateway by the action — Catalog never reads media tables.
            ProductImportSchema::REQ_MEDIA_ID => ['required', 'integer'],
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
