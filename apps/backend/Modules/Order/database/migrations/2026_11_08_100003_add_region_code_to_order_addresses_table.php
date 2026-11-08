<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;

return new class extends Migration
{
    /**
     * Checkout snapshot column (gap-C1a D3): freezes the region that was
     * actually taxed — historical snapshots stay valid and untouched
     * through later seed changes.
     */
    public function up(): void
    {
        Schema::table(OrderAddressSchema::TABLE, function (Blueprint $table): void {
            $table->string(OrderAddressSchema::REGION_CODE, 10)->nullable()->after(OrderAddressSchema::COUNTRY_CODE);
        });
    }

    public function down(): void
    {
        Schema::table(OrderAddressSchema::TABLE, function (Blueprint $table): void {
            $table->dropColumn(OrderAddressSchema::REGION_CODE);
        });
    }
};
