<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Order\OrderSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(OrderSchema::TABLE, function (Blueprint $table) {
            // What the customer saw at checkout when a non-base display
            // currency was selected. Amounts stay in the base currency.
            $table->string(OrderSchema::DISPLAY_CURRENCY_CODE, 3)->nullable();
            $table->decimal(OrderSchema::DISPLAY_FX_RATE, 16, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(OrderSchema::TABLE, function (Blueprint $table) {
            $table->dropColumn([OrderSchema::DISPLAY_CURRENCY_CODE, OrderSchema::DISPLAY_FX_RATE]);
        });
    }
};
