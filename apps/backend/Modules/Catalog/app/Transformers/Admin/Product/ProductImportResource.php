<?php

namespace Modules\Catalog\Transformers\Admin\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CatalogImport;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;

/** @mixin CatalogImport */
class ProductImportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  CatalogImport  $this->resource
     */
    public function toArray(Request $request): array
    {
        return [
            CatalogImportSchema::ID => $this->resource->{CatalogImportSchema::ID},
            CatalogImportSchema::USER_ID => $this->resource->{CatalogImportSchema::USER_ID},
            CatalogImportSchema::MEDIA_ID => $this->resource->{CatalogImportSchema::MEDIA_ID},
            CatalogImportSchema::FILENAME => $this->resource->{CatalogImportSchema::FILENAME},
            CatalogImportSchema::STATUS => $this->resource->{CatalogImportSchema::STATUS},
            CatalogImportSchema::STRATEGY => $this->resource->{CatalogImportSchema::STRATEGY},
            CatalogImportSchema::DRY_RUN => $this->resource->{CatalogImportSchema::DRY_RUN},
            CatalogImportSchema::TOTAL_ROWS => $this->resource->{CatalogImportSchema::TOTAL_ROWS},
            CatalogImportSchema::CREATED_COUNT => $this->resource->{CatalogImportSchema::CREATED_COUNT},
            CatalogImportSchema::UPDATED_COUNT => $this->resource->{CatalogImportSchema::UPDATED_COUNT},
            CatalogImportSchema::SKIPPED_COUNT => $this->resource->{CatalogImportSchema::SKIPPED_COUNT},
            CatalogImportSchema::FAILED_COUNT => $this->resource->{CatalogImportSchema::FAILED_COUNT},
            CatalogImportSchema::DURATION_MS => $this->resource->{CatalogImportSchema::DURATION_MS},
            CatalogImportSchema::ERRORS => $this->resource->{CatalogImportSchema::ERRORS},
            CatalogImportSchema::CREATED_AT => $this->resource->{CatalogImportSchema::CREATED_AT}?->toIso8601String(),
        ];
    }
}
