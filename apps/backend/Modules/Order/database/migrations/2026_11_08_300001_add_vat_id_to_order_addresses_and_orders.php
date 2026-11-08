<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;

return new class extends Migration
{
    /**
     * Frozen VAT evidence (gap-C1c D4): the submitted identifier is
     * snapshotted on the order; later customer edits never rewrite
     * history (policy: money is reproducible).
     */
    public function up(): void
    {
        Schema::table(OrderAddressSchema::TABLE, function (Blueprint $table): void {
            $table->string(OrderAddressSchema::VAT_ID, 32)->nullable()->after(OrderAddressSchema::REGION_CODE);
        });

        Schema::table(OrderSchema::TABLE, function (Blueprint $table): void {
            $table->string(OrderSchema::BUYER_VAT_ID, 32)->nullable()->after(OrderSchema::PRICES_CONTAIN_TAX);
        });
    }

    public function down(): void
    {
        Schema::table(OrderAddressSchema::TABLE, function (Blueprint $table): void {
            $table->dropColumn(OrderAddressSchema::VAT_ID);
        });

        Schema::table(OrderSchema::TABLE, function (Blueprint $table): void {
            $table->dropColumn(OrderSchema::BUYER_VAT_ID);
        });
    }
};
