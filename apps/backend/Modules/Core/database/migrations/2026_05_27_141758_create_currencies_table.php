<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Currency\CurrencySchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(CurrencySchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->char(CurrencySchema::CODE, 3)->unique();
            $table->string(CurrencySchema::NAME);
            $table->string(CurrencySchema::NATIVE_NAME)->nullable();
            $table->unsignedTinyInteger(CurrencySchema::DECIMAL_PLACES)->default(2);
            $table->string(CurrencySchema::SYMBOL)->nullable();
            $table->boolean(CurrencySchema::IS_DEFAULT)->default(false);
            $table->boolean(CurrencySchema::IS_SYMBOL_RIGHT)->default(false);
            // Display-conversion rate against the base currency; seeded
            // currencies all start at 1.00000000 until an admin sets real
            // rates. Charging stays in the base currency regardless.
            $table->decimal(CurrencySchema::EXCHANGE_RATE, 16, 8)->default('1.00000000');
            $table->boolean(CurrencySchema::IS_ACTIVE)->default(true);
            $table->timestamps();
        });

        DB::statement(
            'CREATE UNIQUE INDEX core_currencies_one_default_unique ON '.CurrencySchema::TABLE
            .' ('.CurrencySchema::IS_DEFAULT.') WHERE '.CurrencySchema::IS_DEFAULT
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CurrencySchema::TABLE);
    }
};
