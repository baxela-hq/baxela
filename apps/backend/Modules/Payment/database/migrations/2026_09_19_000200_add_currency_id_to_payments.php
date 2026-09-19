<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Payment\Schemas\Payment\PaymentSchema;

return new class extends Migration
{
    /**
     * Currency of the payment amount, snapshotted from the order at creation;
     * existing rows fall back to the current default currency (their orders
     * were backfilled to the same value).
     */
    public function up(): void
    {
        if (! Schema::hasColumn(PaymentSchema::TABLE, PaymentSchema::CURRENCY_ID)) {
            Schema::table(PaymentSchema::TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger(PaymentSchema::CURRENCY_ID)->nullable()->index();
            });
        }

        $defaultCurrencyId = DB::table(CurrencySchema::TABLE)
            ->where(CurrencySchema::IS_DEFAULT, true)
            ->value(CurrencySchema::ID);

        if (! is_null($defaultCurrencyId)) {
            DB::table(PaymentSchema::TABLE)
                ->whereNull(PaymentSchema::CURRENCY_ID)
                ->update([PaymentSchema::CURRENCY_ID => $defaultCurrencyId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(PaymentSchema::TABLE, PaymentSchema::CURRENCY_ID)) {
            Schema::table(PaymentSchema::TABLE, function (Blueprint $table): void {
                $table->dropIndex([PaymentSchema::CURRENCY_ID]);
                $table->dropColumn(PaymentSchema::CURRENCY_ID);
            });
        }
    }
};
