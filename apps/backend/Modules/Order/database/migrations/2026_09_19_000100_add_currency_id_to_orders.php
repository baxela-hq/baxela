<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Order\Schemas\Order\OrderSchema;

return new class extends Migration
{
    /**
     * Snapshot the currency the order was placed in (fresh databases get the
     * column from the create migration); existing rows fall back to the
     * current default currency.
     */
    public function up(): void
    {
        if (! Schema::hasColumn(OrderSchema::TABLE, OrderSchema::CURRENCY_ID)) {
            Schema::table(OrderSchema::TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger(OrderSchema::CURRENCY_ID)->nullable()->index();
            });
        }

        $defaultCurrencyId = DB::table(CurrencySchema::TABLE)
            ->where(CurrencySchema::IS_DEFAULT, true)
            ->value(CurrencySchema::ID);

        if (! is_null($defaultCurrencyId)) {
            DB::table(OrderSchema::TABLE)
                ->whereNull(OrderSchema::CURRENCY_ID)
                ->update([OrderSchema::CURRENCY_ID => $defaultCurrencyId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(OrderSchema::TABLE, OrderSchema::CURRENCY_ID)) {
            Schema::table(OrderSchema::TABLE, function (Blueprint $table): void {
                $table->dropIndex([OrderSchema::CURRENCY_ID]);
                $table->dropColumn(OrderSchema::CURRENCY_ID);
            });
        }
    }
};
