<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Discount\Schemas\Promotion\PromotionSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PromotionSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(PromotionSchema::NAME);
            $table->enum(PromotionSchema::SCOPE, ['all', 'specific']);
            $table->enum(PromotionSchema::TYPE, ['percent', 'fixed']);
            // percent: 15.00 == 15%; fixed: major-unit amount off each unit
            $table->decimal(PromotionSchema::VALUE, 12, 2)->unsigned();
            // Validity window, UTC, inclusive bounds
            $table->datetime(PromotionSchema::STARTS_AT)->nullable();
            $table->datetime(PromotionSchema::ENDS_AT)->nullable();
            $table->unsignedSmallInteger(PromotionSchema::PRIORITY)->default(0);
            $table->boolean(PromotionSchema::IS_ACTIVE)->default(true);
            $table->timestamps();
            // The resolver's active-window lookup
            $table->index([PromotionSchema::IS_ACTIVE, PromotionSchema::STARTS_AT, PromotionSchema::ENDS_AT]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PromotionSchema::TABLE);
    }
};
