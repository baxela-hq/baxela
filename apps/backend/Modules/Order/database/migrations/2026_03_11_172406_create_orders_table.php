<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OrderSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(OrderSchema::USER_ID)->index();
            $table->string(OrderSchema::ORDER_CODE)->unique();
            $table->enum(OrderSchema::STATUS, OrderStatusEnum::cases());
            $table->enum(OrderSchema::PAYMENT_STATUS, OrderPaymentStatusEnum::cases())
                ->default(OrderPaymentStatusEnum::UNPAID->value);
            $table->timestamp(OrderSchema::PAID_AT)->nullable();
            $table->decimal(OrderSchema::TOTAL_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->unsignedBigInteger(OrderSchema::CURRENCY_ID)->nullable()->index();
            $table->unsignedBigInteger(OrderSchema::SHIPPING_METHOD_ID)->nullable();
            $table->string(OrderSchema::SHIPPING_METHOD_NAME)->nullable();
            $table->decimal(OrderSchema::SHIPPING_COST, 12, 2)->unsigned()->default(0.00);
            // Coupon snapshot: what was redeemed at purchase time. Deliberately
            // minimal (no type/value/cap copies) — later coupon edits must
            // never rewrite what a past order received. coupon_id is a plain
            // reference, not a cross-module FK.
            $table->unsignedBigInteger(OrderSchema::COUPON_ID)->nullable()->index();
            $table->string(OrderSchema::COUPON_CODE)->nullable();
            $table->decimal(OrderSchema::DISCOUNT_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->timestamp(OrderSchema::EXPIRES_AT)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(OrderSchema::TABLE);
    }
};
