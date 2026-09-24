<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(RedemptionSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(RedemptionSchema::COUPON_ID)
                ->constrained(CouponSchema::TABLE)->onDelete('cascade');
            // Deliberately no cross-module FK to orders — the Discount module
            // knows orders only by id through the gateway contract
            $table->unsignedBigInteger(RedemptionSchema::ORDER_ID)->unique();
            $table->unsignedBigInteger(RedemptionSchema::USER_ID)->index();
            $table->decimal(RedemptionSchema::DISCOUNT_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(RedemptionSchema::TABLE);
    }
};
