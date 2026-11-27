<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(CartItemSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(CartItemSchema::CART_ID);
            $table->foreign([TenantColumn::TENANT_ID, CartItemSchema::CART_ID], 'cart_items_tenant_cart_fk')
                ->references([TenantColumn::TENANT_ID, CartSchema::ID])
                ->on(CartSchema::TABLE)->cascadeOnDelete();
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
