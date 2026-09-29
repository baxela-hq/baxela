<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\ProductImport\ProductImportSchema;
use Modules\Catalog\Schemas\ProductImport\ProductImportStatusEnum;
use Modules\Catalog\Schemas\ProductImport\ProductImportStrategyEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(ProductImportSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // Plain unsigned ints, mirroring the catalog_images precedent:
            // cross-module ids stay denormalized, without FK constraints.
            $table->unsignedBigInteger(ProductImportSchema::USER_ID)->index();
            $table->unsignedBigInteger(ProductImportSchema::MEDIA_ID)->index();
            $table->string(ProductImportSchema::FILENAME);
            $table->enum(ProductImportSchema::STATUS, ProductImportStatusEnum::cases())
                ->default(ProductImportStatusEnum::COMPLETED->value);
            $table->enum(ProductImportSchema::STRATEGY, ProductImportStrategyEnum::cases())
                ->default(ProductImportStrategyEnum::UPDATE->value);
            $table->boolean(ProductImportSchema::DRY_RUN)->default(false);
            $table->unsignedInteger(ProductImportSchema::TOTAL_ROWS)->default(0);
            $table->unsignedInteger(ProductImportSchema::CREATED_COUNT)->default(0);
            $table->unsignedInteger(ProductImportSchema::UPDATED_COUNT)->default(0);
            $table->unsignedInteger(ProductImportSchema::SKIPPED_COUNT)->default(0);
            $table->unsignedInteger(ProductImportSchema::FAILED_COUNT)->default(0);
            $table->unsignedInteger(ProductImportSchema::DURATION_MS)->default(0);
            $table->json(ProductImportSchema::ERRORS)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ProductImportSchema::TABLE);
    }
};
