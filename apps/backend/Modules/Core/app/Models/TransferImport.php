<?php

namespace Modules\Core\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Schemas\TransferImport\TransferImportStatusEnum;

/**
 * @property int $id
 * @property int $user_id
 * @property string $module
 * @property string $filename
 * @property TransferImportStatusEnum $status
 * @property TransferStrategyEnum $strategy
 * @property bool $dry_run
 * @property int $total_rows
 * @property int $created_count
 * @property int $updated_count
 * @property int $skipped_count
 * @property int $failed_count
 * @property int $duration_ms
 * @property array<int, mixed>|null $errors
 * @property array<string, mixed>|null $summary
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
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
