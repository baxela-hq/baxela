<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OrderItemSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(OrderItemSchema::ORDER_ID)->constrained(OrderSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(OrderItemSchema::VARIANT_ID);
            $table->string(OrderItemSchema::PRODUCT_NAME_SNAPSHOT);
            $table->string(OrderItemSchema::PRODUCT_SLUG_SNAPSHOT)->nullable();
            $table->decimal(OrderItemSchema::PRICE_SNAPSHOT, 12, 2)->unsigned()->default(0.00);
            $table->unsignedTinyInteger(OrderItemSchema::QUANTITY)->default(0);
            $table->timestamp(OrderItemSchema::CREATED_AT)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(OrderItemSchema::TABLE);
    }
};
