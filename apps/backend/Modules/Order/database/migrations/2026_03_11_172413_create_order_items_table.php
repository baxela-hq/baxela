<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
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
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(OrderItemSchema::ORDER_ID);
            $table->foreign([TenantColumn::TENANT_ID, OrderItemSchema::ORDER_ID], 'order_items_tenant_order_fk')
                ->references([TenantColumn::TENANT_ID, OrderSchema::ID])
                ->on(OrderSchema::TABLE)->cascadeOnDelete();
            $table->unsignedBigInteger(OrderItemSchema::VARIANT_ID);
            $table->string(OrderItemSchema::PRODUCT_NAME_SNAPSHOT);
            $table->string(OrderItemSchema::PRODUCT_SLUG_SNAPSHOT)->nullable();
            $table->decimal(OrderItemSchema::PRICE_SNAPSHOT, 12, 2)->unsigned()->default(0.00);
            $table->unsignedTinyInteger(OrderItemSchema::QUANTITY)->default(0);
            // Tax snapshot — zeros when untaxed, never null (null would be
            // ambiguous against an explicit zero rate)
            $table->decimal(OrderItemSchema::TAX_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->decimal(OrderItemSchema::TAX_RATE, 7, 4)->unsigned()->default(0.0000);
            $table->unsignedBigInteger(OrderItemSchema::TAX_CLASS_ID)->nullable();
            // Additive promotion-history columns — nullable: legacy and
            // non-promoted lines simply have no promotion facts
            $table->decimal(OrderItemSchema::BASE_PRICE, 12, 2)->unsigned()->nullable();
            $table->decimal(OrderItemSchema::PROMOTION_DISCOUNT, 12, 2)->unsigned()->nullable();
            // Plain discount promotion id — no cross-module FK by convention
            $table->unsignedBigInteger(OrderItemSchema::PROMOTION_ID)->nullable();
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
