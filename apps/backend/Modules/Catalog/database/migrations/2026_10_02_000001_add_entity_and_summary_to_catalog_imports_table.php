<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportEntityEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(CatalogImportSchema::TABLE, function (Blueprint $table) {
            // The audit table serves both the product CSV import and the
            // whole-module JSON data transfer; entity separates their runs.
            $table->enum(CatalogImportSchema::ENTITY, CatalogImportEntityEnum::cases())
                ->default(CatalogImportEntityEnum::PRODUCT->value)->after(CatalogImportSchema::FILENAME);
            // Per-section counts for multi-section (JSON) runs.
            $table->json(CatalogImportSchema::SUMMARY)->nullable()->after(CatalogImportSchema::ERRORS);
        });
    }

    public function down(): void
    {
        Schema::table(CatalogImportSchema::TABLE, function (Blueprint $table) {
            $table->dropColumn([CatalogImportSchema::ENTITY, CatalogImportSchema::SUMMARY]);
        });
    }
};
