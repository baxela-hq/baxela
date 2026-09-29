<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Database\Factories\ProductImportFactory;
use Modules\Catalog\Schemas\ProductImport\ProductImportSchema;
use Modules\Catalog\Schemas\ProductImport\ProductImportStatusEnum;
use Modules\Catalog\Schemas\ProductImport\ProductImportStrategyEnum;

class ProductImport extends Model
{
    use HasFactory;

    protected $table = ProductImportSchema::TABLE;

    protected $fillable = [
        ProductImportSchema::USER_ID,
        ProductImportSchema::MEDIA_ID,
        ProductImportSchema::FILENAME,
        ProductImportSchema::STATUS,
        ProductImportSchema::STRATEGY,
        ProductImportSchema::DRY_RUN,
        ProductImportSchema::TOTAL_ROWS,
        ProductImportSchema::CREATED_COUNT,
        ProductImportSchema::UPDATED_COUNT,
        ProductImportSchema::SKIPPED_COUNT,
        ProductImportSchema::FAILED_COUNT,
        ProductImportSchema::DURATION_MS,
        ProductImportSchema::ERRORS,
    ];

    protected function casts(): array
    {
        return [
            ProductImportSchema::STATUS => ProductImportStatusEnum::class,
            ProductImportSchema::STRATEGY => ProductImportStrategyEnum::class,
            ProductImportSchema::DRY_RUN => 'boolean',
            ProductImportSchema::ERRORS => 'array',
        ];
    }

    protected static function newFactory(): ProductImportFactory
    {
        return ProductImportFactory::new();
    }
}
