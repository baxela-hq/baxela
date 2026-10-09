<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Discount\Schemas\Promotion\PromotionCategorySchema;
use Modules\Discount\Schemas\Promotion\PromotionSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PromotionCategorySchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(PromotionCategorySchema::PROMOTION_ID)
                ->constrained(PromotionSchema::TABLE)->cascadeOnDelete();
            // Plain catalog category id — no cross-module FK by convention
            $table->unsignedBigInteger(PromotionCategorySchema::CATEGORY_ID);
            $table->unique([PromotionCategorySchema::PROMOTION_ID, PromotionCategorySchema::CATEGORY_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PromotionCategorySchema::TABLE);
    }
};
