<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            $table->enum(OrderSchema::STATUS, OrderStatusEnum::cases());
            $table->decimal(OrderSchema::TOTAL_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->unsignedBigInteger(OrderSchema::SHIPPING_METHOD_ID)->nullable();
            $table->string(OrderSchema::SHIPPING_METHOD_NAME)->nullable();
            $table->decimal(OrderSchema::SHIPPING_COST, 12, 2)->unsigned()->default(0.00);
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
