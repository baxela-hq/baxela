<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;

return new class extends Migration
{
    /**
     * Invoices copy the order's frozen pricing mode at issuance
     * (gap-C1b D5) — documents stay self-contained; the figures they
     * carry were already frozen by the order.
     */
    public function up(): void
    {
        Schema::table(OrderInvoiceSchema::TABLE, function (Blueprint $table): void {
            $table->boolean(OrderInvoiceSchema::PRICES_CONTAIN_TAX)->default(false)->after(OrderInvoiceSchema::TAX_AMOUNT);
        });
    }

    public function down(): void
    {
        Schema::table(OrderInvoiceSchema::TABLE, function (Blueprint $table): void {
            $table->dropColumn(OrderInvoiceSchema::PRICES_CONTAIN_TAX);
        });
    }
};
