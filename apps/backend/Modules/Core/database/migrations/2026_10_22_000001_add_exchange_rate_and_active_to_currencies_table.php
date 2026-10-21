<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Currency\CurrencySchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(CurrencySchema::TABLE, function (Blueprint $table) {
            // Display-conversion rate against the base currency; seeded
            // currencies all start at 1.00000000 until an admin sets real
            // rates. Charging stays in the base currency regardless.
            $table->decimal(CurrencySchema::EXCHANGE_RATE, 16, 8)->default('1.00000000');
            $table->boolean(CurrencySchema::IS_ACTIVE)->default(true);
        });
    }

    public function down(): void
    {
        Schema::table(CurrencySchema::TABLE, function (Blueprint $table) {
            $table->dropColumn([CurrencySchema::EXCHANGE_RATE, CurrencySchema::IS_ACTIVE]);
        });
    }
};
