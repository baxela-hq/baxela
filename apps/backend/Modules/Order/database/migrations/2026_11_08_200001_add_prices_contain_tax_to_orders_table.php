<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Order\OrderSchema;

return new class extends Migration
{
    /**
     * Pricing-mode snapshot (gap-C1b D5): false is the historical default
     * — every pre-existing order was priced exclusive. A mid-life mode
     * flip never rewrites frozen figures (policy: money is reproducible).
     */
    public function up(): void
    {
        Schema::table(OrderSchema::TABLE, function (Blueprint $table): void {
            $table->boolean(OrderSchema::PRICES_CONTAIN_TAX)->default(false)->after(OrderSchema::TAX_AMOUNT);
        });
    }

    public function down(): void
    {
        Schema::table(OrderSchema::TABLE, function (Blueprint $table): void {
            $table->dropColumn(OrderSchema::PRICES_CONTAIN_TAX);
        });
    }
};
