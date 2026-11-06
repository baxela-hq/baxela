<?php

namespace Modules\Transfer\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Transfer\Schemas\TransferImport\TransferImportSchema;
use Modules\Transfer\Schemas\TransferImport\TransferImportStatusEnum;

class TransferImport extends Model
{
    protected $table = TransferImportSchema::TABLE;

    protected $fillable = [
        TransferImportSchema::USER_ID,
        TransferImportSchema::MODULE,
        TransferImportSchema::FILENAME,
        TransferImportSchema::STATUS,
        TransferImportSchema::STRATEGY,
        TransferImportSchema::DRY_RUN,
        TransferImportSchema::TOTAL_ROWS,
        TransferImportSchema::CREATED_COUNT,
        TransferImportSchema::UPDATED_COUNT,
        TransferImportSchema::SKIPPED_COUNT,
        TransferImportSchema::FAILED_COUNT,
        TransferImportSchema::DURATION_MS,
        TransferImportSchema::ERRORS,
        TransferImportSchema::SUMMARY,
    ];

    protected function casts(): array
    {
        return [
            TransferImportSchema::STATUS => TransferImportStatusEnum::class,
            TransferImportSchema::STRATEGY => TransferStrategyEnum::class,
            TransferImportSchema::DRY_RUN => 'boolean',
            TransferImportSchema::ERRORS => 'array',
            TransferImportSchema::SUMMARY => 'array',
        ];
    }
}
