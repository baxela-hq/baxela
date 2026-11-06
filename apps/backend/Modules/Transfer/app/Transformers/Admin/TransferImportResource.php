<?php

namespace Modules\Transfer\Transformers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Transfer\Models\TransferImport;
use Modules\Transfer\Schemas\TransferImport\TransferImportSchema;

/** @mixin TransferImport */
class TransferImportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  TransferImport  $this->resource
     */
    public function toArray(Request $request): array
    {
        return [
            TransferImportSchema::ID => $this->resource->{TransferImportSchema::ID},
            TransferImportSchema::USER_ID => $this->resource->{TransferImportSchema::USER_ID},
            TransferImportSchema::MODULE => $this->resource->{TransferImportSchema::MODULE},
            TransferImportSchema::FILENAME => $this->resource->{TransferImportSchema::FILENAME},
            TransferImportSchema::STATUS => $this->resource->{TransferImportSchema::STATUS},
            TransferImportSchema::STRATEGY => $this->resource->{TransferImportSchema::STRATEGY},
            TransferImportSchema::DRY_RUN => $this->resource->{TransferImportSchema::DRY_RUN},
            TransferImportSchema::TOTAL_ROWS => $this->resource->{TransferImportSchema::TOTAL_ROWS},
            TransferImportSchema::CREATED_COUNT => $this->resource->{TransferImportSchema::CREATED_COUNT},
            TransferImportSchema::UPDATED_COUNT => $this->resource->{TransferImportSchema::UPDATED_COUNT},
            TransferImportSchema::SKIPPED_COUNT => $this->resource->{TransferImportSchema::SKIPPED_COUNT},
            TransferImportSchema::FAILED_COUNT => $this->resource->{TransferImportSchema::FAILED_COUNT},
            TransferImportSchema::DURATION_MS => $this->resource->{TransferImportSchema::DURATION_MS},
            TransferImportSchema::ERRORS => $this->resource->{TransferImportSchema::ERRORS},
            TransferImportSchema::SUMMARY => $this->resource->{TransferImportSchema::SUMMARY},
            TransferImportSchema::CREATED_AT => $this->resource->{TransferImportSchema::CREATED_AT}?->toIso8601String(),
        ];
    }
}
