<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Discount\Schemas\Coupon\CouponSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(CouponSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // Customer-facing code; normalized to uppercase-trimmed on write
            $table->string(CouponSchema::CODE)->unique();
            $table->string(CouponSchema::NAME)->nullable();
            $table->enum(CouponSchema::TYPE, ['percent', 'fixed']);
            // percent: 15.00 == 15%; fixed: major-unit amount off the subtotal
            $table->decimal(CouponSchema::VALUE, 12, 2)->unsigned();
            $table->decimal(CouponSchema::MAX_DISCOUNT_AMOUNT, 12, 2)->unsigned()->nullable();
            $table->decimal(CouponSchema::MIN_ORDER_AMOUNT, 12, 2)->unsigned()->nullable();
            // Validity window, UTC, inclusive bounds
            $table->datetime(CouponSchema::STARTS_AT)->nullable();
            $table->datetime(CouponSchema::ENDS_AT)->nullable();
            $table->unsignedInteger(CouponSchema::USAGE_LIMIT)->nullable();
            $table->unsignedInteger(CouponSchema::PER_USER_LIMIT)->nullable();
            // Denormalized mirror of retained redemptions — mutations only
            // under the coupon row lock (DiscountGateway)
            $table->unsignedInteger(CouponSchema::USAGE_COUNT)->default(0);
            $table->boolean(CouponSchema::IS_ACTIVE)->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CouponSchema::TABLE);
    }
};
