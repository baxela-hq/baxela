<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Discount\Schemas\Promotion\PromotionProductSchema;
use Modules\Discount\Schemas\Promotion\PromotionSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PromotionProductSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(PromotionProductSchema::PROMOTION_ID)
                ->constrained(PromotionSchema::TABLE)->cascadeOnDelete();
            // Plain catalog product id — no cross-module FK by convention
            $table->unsignedBigInteger(PromotionProductSchema::PRODUCT_ID);
            $table->unique([PromotionProductSchema::PROMOTION_ID, PromotionProductSchema::PRODUCT_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PromotionProductSchema::TABLE);
    }
};
