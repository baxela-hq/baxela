<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStatusEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStrategyEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(CatalogImportSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // Plain unsigned ints, mirroring the catalog_images precedent:
            // cross-module ids stay denormalized, without FK constraints.
            $table->unsignedBigInteger(CatalogImportSchema::USER_ID)->index();
            $table->unsignedBigInteger(CatalogImportSchema::MEDIA_ID)->index();
            $table->string(CatalogImportSchema::FILENAME);
            $table->enum(CatalogImportSchema::STATUS, CatalogImportStatusEnum::cases())
                ->default(CatalogImportStatusEnum::COMPLETED->value);
            $table->enum(CatalogImportSchema::STRATEGY, CatalogImportStrategyEnum::cases())
                ->default(CatalogImportStrategyEnum::UPDATE->value);
            $table->boolean(CatalogImportSchema::DRY_RUN)->default(false);
            $table->unsignedInteger(CatalogImportSchema::TOTAL_ROWS)->default(0);
            $table->unsignedInteger(CatalogImportSchema::CREATED_COUNT)->default(0);
            $table->unsignedInteger(CatalogImportSchema::UPDATED_COUNT)->default(0);
            $table->unsignedInteger(CatalogImportSchema::SKIPPED_COUNT)->default(0);
            $table->unsignedInteger(CatalogImportSchema::FAILED_COUNT)->default(0);
            $table->unsignedInteger(CatalogImportSchema::DURATION_MS)->default(0);
            $table->json(CatalogImportSchema::ERRORS)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CatalogImportSchema::TABLE);
    }
};
