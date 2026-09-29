<?php

namespace Modules\Catalog\Transformers\Admin\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductImport;
use Modules\Catalog\Schemas\ProductImport\ProductImportSchema;

/** @mixin ProductImport */
class ProductImportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  ProductImport  $this->resource
     */
    public function toArray(Request $request): array
    {
        return [
            ProductImportSchema::ID => $this->resource->{ProductImportSchema::ID},
            ProductImportSchema::USER_ID => $this->resource->{ProductImportSchema::USER_ID},
            ProductImportSchema::MEDIA_ID => $this->resource->{ProductImportSchema::MEDIA_ID},
            ProductImportSchema::FILENAME => $this->resource->{ProductImportSchema::FILENAME},
            ProductImportSchema::STATUS => $this->resource->{ProductImportSchema::STATUS},
            ProductImportSchema::STRATEGY => $this->resource->{ProductImportSchema::STRATEGY},
            ProductImportSchema::DRY_RUN => $this->resource->{ProductImportSchema::DRY_RUN},
            ProductImportSchema::TOTAL_ROWS => $this->resource->{ProductImportSchema::TOTAL_ROWS},
            ProductImportSchema::CREATED_COUNT => $this->resource->{ProductImportSchema::CREATED_COUNT},
            ProductImportSchema::UPDATED_COUNT => $this->resource->{ProductImportSchema::UPDATED_COUNT},
            ProductImportSchema::SKIPPED_COUNT => $this->resource->{ProductImportSchema::SKIPPED_COUNT},
            ProductImportSchema::FAILED_COUNT => $this->resource->{ProductImportSchema::FAILED_COUNT},
            ProductImportSchema::DURATION_MS => $this->resource->{ProductImportSchema::DURATION_MS},
            ProductImportSchema::ERRORS => $this->resource->{ProductImportSchema::ERRORS},
            ProductImportSchema::CREATED_AT => $this->resource->{ProductImportSchema::CREATED_AT}?->toIso8601String(),
        ];
    }
}
