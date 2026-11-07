<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;

return new class extends Migration
{
    /**
     * Gateway refund coverage (gap-B4): persist the gateway-side payment
     * identifier the refund APIs key on, and widen the ledger states with
     * `processing` (accepted, awaiting the gateway's webhook) and
     * `reversed` (a completed refund returned to the merchant).
     */
    public function up(): void
    {
        Schema::table(PaymentSchema::TABLE, function (Blueprint $table) {
            $table->string(PaymentSchema::GATEWAY_TRANSACTION_ID)->nullable();
        });

        Schema::table(PaymentRefundSchema::TABLE, function (Blueprint $table) {
            // The ledger states gain processing/reversed; fresh installs
            // already get the full enum from the create migration (it reads
            // the enum cases at runtime), existing databases alter here.
            $table->enum(PaymentRefundSchema::STATUS, PaymentRefundStatusEnum::cases())
                ->default(PaymentRefundStatusEnum::PENDING->value)
                ->change();
            // Gateway-refund-id lookups (manual reconciliation).
            $table->index(PaymentRefundSchema::TRANSACTION_ID);
        });
    }

    public function down(): void
    {
        Schema::table(PaymentRefundSchema::TABLE, function (Blueprint $table) {
            $table->dropIndex([PaymentRefundSchema::TRANSACTION_ID]);
        });

        Schema::table(PaymentSchema::TABLE, function (Blueprint $table) {
            $table->dropColumn(PaymentSchema::GATEWAY_TRANSACTION_ID);
        });
    }
};
