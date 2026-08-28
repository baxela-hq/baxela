<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PaymentSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(PaymentSchema::ORDER_ID)->index();
            $table->unsignedBigInteger(PaymentSchema::TRANSACTION_ID)->nullable();
            $table->string(PaymentSchema::METHOD);
            $table->decimal(PaymentSchema::AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->enum(PaymentSchema::STATUS, PaymentStatusEnum::cases());
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PaymentSchema::TABLE);
    }
};
