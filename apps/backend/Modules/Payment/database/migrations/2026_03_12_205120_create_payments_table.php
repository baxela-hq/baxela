<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
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
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            // Composite parent key for the layer-3 tenant-safe refund FK.
            $table->unique([TenantColumn::TENANT_ID, PaymentSchema::ID], 'payment_payments_tenant_id_unique');
            $table->unsignedBigInteger(PaymentSchema::ORDER_ID)->index();
            // Alphanumeric gateway ids (Stripe cs_…, PayPal 5O190…), not ints.
            $table->string(PaymentSchema::TRANSACTION_ID)->nullable();
            // Gateway refund coverage (gap-B4): the gateway-side payment
            // identifier the refund APIs key on.
            $table->string(PaymentSchema::GATEWAY_TRANSACTION_ID)->nullable();
            $table->string(PaymentSchema::METHOD);
            $table->decimal(PaymentSchema::AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->unsignedBigInteger(PaymentSchema::CURRENCY_ID)->nullable()->index();
            // Transaction-time currency identity (§6.7.1): code and rate are
            // snapshots, not live reads of the reference currency.
            $table->string(PaymentSchema::CURRENCY_CODE, 3)->nullable();
            $table->decimal(PaymentSchema::EXCHANGE_RATE, 16, 8)->nullable();
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
