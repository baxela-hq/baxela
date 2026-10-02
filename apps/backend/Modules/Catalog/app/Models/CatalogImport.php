<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Database\Factories\CatalogImportFactory;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportEntityEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStatusEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStrategyEnum;

class CatalogImport extends Model
{
    use HasFactory;

    protected $table = CatalogImportSchema::TABLE;

    protected $fillable = [
        CatalogImportSchema::USER_ID,
        CatalogImportSchema::MEDIA_ID,
        CatalogImportSchema::FILENAME,
        CatalogImportSchema::ENTITY,
        CatalogImportSchema::STATUS,
        CatalogImportSchema::STRATEGY,
        CatalogImportSchema::DRY_RUN,
        CatalogImportSchema::TOTAL_ROWS,
        CatalogImportSchema::CREATED_COUNT,
        CatalogImportSchema::UPDATED_COUNT,
        CatalogImportSchema::SKIPPED_COUNT,
        CatalogImportSchema::FAILED_COUNT,
        CatalogImportSchema::DURATION_MS,
        CatalogImportSchema::ERRORS,
        CatalogImportSchema::SUMMARY,
    ];

    protected function casts(): array
    {
        return [
            CatalogImportSchema::ENTITY => CatalogImportEntityEnum::class,
            CatalogImportSchema::STATUS => CatalogImportStatusEnum::class,
            CatalogImportSchema::STRATEGY => CatalogImportStrategyEnum::class,
            CatalogImportSchema::DRY_RUN => 'boolean',
            CatalogImportSchema::ERRORS => 'array',
            CatalogImportSchema::SUMMARY => 'array',
        ];
    }

    protected static function newFactory(): CatalogImportFactory
    {
        return CatalogImportFactory::new();
    }
}
