<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            $table->uuid(PaymentRefundSchema::UUID)->unique();
            // Financial records must survive their parents: NO cascade —
            // payments/orders cannot be deleted while refunds reference them
            $table->foreignId(PaymentRefundSchema::PAYMENT_ID)
                ->constrained(PaymentSchema::TABLE)->restrictOnDelete();
            $table->foreignId(PaymentRefundSchema::ORDER_ID)
                ->constrained(OrderSchema::TABLE)->restrictOnDelete();
            $table->decimal(PaymentRefundSchema::AMOUNT, 12, 2)->unsigned();
            $table->string(PaymentRefundSchema::REASON);
            $table->enum(PaymentRefundSchema::STATUS, PaymentRefundStatusEnum::cases())
                ->default(PaymentRefundStatusEnum::PENDING->value);
            $table->string(PaymentRefundSchema::TRANSACTION_ID)->nullable();
            $table->unsignedBigInteger(PaymentRefundSchema::REFUNDED_BY)->nullable();
            $table->timestamps();

            $table->index(PaymentRefundSchema::STATUS);
            $table->index(PaymentRefundSchema::ORDER_ID);
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
