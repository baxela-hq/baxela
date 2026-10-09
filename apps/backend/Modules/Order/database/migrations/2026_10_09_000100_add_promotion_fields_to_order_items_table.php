<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(OrderItemSchema::TABLE, function (Blueprint $table) {
            // Additive promotion-history columns — nullable: legacy and
            // non-promoted lines simply have no promotion facts
            $table->decimal(OrderItemSchema::BASE_PRICE, 12, 2)->unsigned()->nullable();
            $table->decimal(OrderItemSchema::PROMOTION_DISCOUNT, 12, 2)->unsigned()->nullable();
            // Plain discount promotion id — no cross-module FK by convention
            $table->unsignedBigInteger(OrderItemSchema::PROMOTION_ID)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(OrderItemSchema::TABLE, function (Blueprint $table) {
            $table->dropColumn([
                OrderItemSchema::BASE_PRICE,
                OrderItemSchema::PROMOTION_DISCOUNT,
                OrderItemSchema::PROMOTION_ID,
            ]);
        });
    }
};
