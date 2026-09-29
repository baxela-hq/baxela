<?php

namespace Modules\Catalog\Transformers\Admin\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, mixed> */
class ProductImportPreviewResource extends JsonResource
{
    /**
     * The preview payload built by PreviewProductImportAction.
     *
     * @param  array<string, mixed>  $this->resource
     */
    public function toArray(Request $request): array
    {
        return [
            'filename' => $this->resource['filename'],
            'delimiter' => $this->resource['delimiter'],
            'headers' => $this->resource['headers'],
            'rowCount' => $this->resource['rowCount'],
            'rowCap' => $this->resource['rowCap'],
            'sampleRows' => $this->resource['sampleRows'],
            'suggestedMapping' => (object) $this->resource['suggestedMapping'],
            'availableFields' => $this->resource['availableFields'],
            'defaultLanguage' => $this->resource['defaultLanguage'],
            'warnings' => $this->resource['warnings'],
        ];
    }
}
