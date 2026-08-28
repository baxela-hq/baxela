<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CurrencySchema::TABLE);
    }
};
