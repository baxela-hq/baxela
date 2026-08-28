<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(CartItemSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(CartItemSchema::CART_ID)->constrained(CartSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(CartItemSchema::VARIANT_ID)->index();
            $table->decimal(CartItemSchema::PRICE_SNAPSHOT, 12, 2)->unsigned()->default(0.00);
            $table->string(CartItemSchema::PRODUCT_NAME_SNAPSHOT);
            $table->unsignedTinyInteger(CartItemSchema::QUANTITY)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CartItemSchema::TABLE);
    }
};
