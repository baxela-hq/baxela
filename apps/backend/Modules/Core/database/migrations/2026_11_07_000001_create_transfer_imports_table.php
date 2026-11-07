<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Schemas\TransferImport\TransferImportStatusEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TransferImportSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // Plain unsigned int, mirroring the catalog_imports precedent:
            // cross-module ids stay denormalized, without FK constraints.
            $table->unsignedBigInteger(TransferImportSchema::USER_ID)->index();
            $table->string(TransferImportSchema::MODULE)->index();
            $table->string(TransferImportSchema::FILENAME);
            $table->enum(TransferImportSchema::STATUS, TransferImportStatusEnum::cases())
                ->default(TransferImportStatusEnum::COMPLETED->value);
            $table->enum(TransferImportSchema::STRATEGY, TransferStrategyEnum::cases())
                ->default(TransferStrategyEnum::UPDATE->value);
            $table->boolean(TransferImportSchema::DRY_RUN)->default(false);
            $table->unsignedInteger(TransferImportSchema::TOTAL_ROWS)->default(0);
            $table->unsignedInteger(TransferImportSchema::CREATED_COUNT)->default(0);
            $table->unsignedInteger(TransferImportSchema::UPDATED_COUNT)->default(0);
            $table->unsignedInteger(TransferImportSchema::SKIPPED_COUNT)->default(0);
            $table->unsignedInteger(TransferImportSchema::FAILED_COUNT)->default(0);
            $table->unsignedInteger(TransferImportSchema::DURATION_MS)->default(0);
            $table->json(TransferImportSchema::ERRORS)->nullable();
            $table->json(TransferImportSchema::SUMMARY)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TransferImportSchema::TABLE);
    }
};
