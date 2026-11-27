<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PaymentRefundSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->uuid(PaymentRefundSchema::UUID)->unique();
            // Financial records must survive their parents: NO cascade —
            // payments/orders cannot be deleted while refunds reference them
            $table->unsignedBigInteger(PaymentRefundSchema::PAYMENT_ID);
            $table->foreign([TenantColumn::TENANT_ID, PaymentRefundSchema::PAYMENT_ID], 'payment_refunds_tenant_payment_fk')
                ->references([TenantColumn::TENANT_ID, PaymentSchema::ID])
                ->on(PaymentSchema::TABLE)->restrictOnDelete();
            $table->foreignId(PaymentRefundSchema::ORDER_ID)
                ->constrained(OrderSchema::TABLE)->restrictOnDelete();
            $table->decimal(PaymentRefundSchema::AMOUNT, 12, 2)->unsigned();
            $table->string(PaymentRefundSchema::CURRENCY_CODE, 3)->nullable();
            $table->decimal(PaymentRefundSchema::EXCHANGE_RATE, 16, 8)->nullable();
            $table->string(PaymentRefundSchema::REASON);
            $table->enum(PaymentRefundSchema::STATUS, PaymentRefundStatusEnum::cases())
                ->default(PaymentRefundStatusEnum::PENDING->value);
            $table->string(PaymentRefundSchema::TRANSACTION_ID)->nullable();
            $table->unsignedBigInteger(PaymentRefundSchema::REFUNDED_BY)->nullable();
            $table->timestamps();

            $table->index(PaymentRefundSchema::STATUS);
            $table->index(PaymentRefundSchema::ORDER_ID);
            // Gateway-refund-id lookups (manual reconciliation).
            $table->index(PaymentRefundSchema::TRANSACTION_ID);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PaymentRefundSchema::TABLE);
    }
};
