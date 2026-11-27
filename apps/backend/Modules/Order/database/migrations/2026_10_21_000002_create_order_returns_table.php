<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnReasonEnum;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OrderReturnSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(OrderReturnSchema::ORDER_ID);
            $table->foreign([TenantColumn::TENANT_ID, OrderReturnSchema::ORDER_ID], 'order_returns_tenant_order_fk')
                ->references([TenantColumn::TENANT_ID, OrderSchema::ID])
                ->on(OrderSchema::TABLE)->cascadeOnDelete();
            $table->unsignedBigInteger(OrderReturnSchema::USER_ID)->index();
            $table->enum(OrderReturnSchema::STATUS, OrderReturnStatusEnum::cases())
                ->default(OrderReturnStatusEnum::REQUESTED->value);
            $table->enum(OrderReturnSchema::REASON, OrderReturnReasonEnum::cases());
            $table->string(OrderReturnSchema::NOTE)->nullable();
            $table->string(OrderReturnSchema::ADMIN_NOTE)->nullable();
            $table->string(OrderReturnSchema::TRACKING_NUMBER)->nullable();
            $table->timestamp(OrderReturnSchema::STOCK_RESTORED_AT)->nullable();
            $table->timestamps();

            $table->index(OrderReturnSchema::STATUS);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(OrderReturnSchema::TABLE);
    }
};
