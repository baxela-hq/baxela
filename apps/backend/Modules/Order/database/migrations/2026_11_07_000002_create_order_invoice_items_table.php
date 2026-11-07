<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Schemas\OrderInvoiceItem\OrderInvoiceItemSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OrderInvoiceItemSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(OrderInvoiceItemSchema::INVOICE_ID)
                ->constrained(OrderInvoiceSchema::TABLE)->onDelete('cascade');

            // Line snapshot (D4/D11): every money column is COPIED from the
            // finalized order-item snapshot at issue time — checkout's
            // rounding and allocation decisions are authoritative.
            $table->string(OrderInvoiceItemSchema::PRODUCT_NAME);
            $table->string(OrderInvoiceItemSchema::PRODUCT_SLUG)->nullable();
            $table->string(OrderInvoiceItemSchema::SKU)->nullable();
            $table->unsignedBigInteger(OrderInvoiceItemSchema::VARIANT_ID)->nullable();
            $table->unsignedInteger(OrderInvoiceItemSchema::QUANTITY);
            $table->decimal(OrderInvoiceItemSchema::UNIT_PRICE, 12, 2)->unsigned()->default(0.00);
            $table->decimal(OrderInvoiceItemSchema::PROMOTION_DISCOUNT, 12, 2)->unsigned()->default(0.00);
            // Copied only if order items persist a per-line coupon
            // allocation; otherwise the coupon lives in the invoice totals
            // block — an allocation is never invented at issue time.
            $table->decimal(OrderInvoiceItemSchema::COUPON_DISCOUNT_ALLOCATED, 12, 2)->unsigned()->nullable();
            $table->decimal(OrderInvoiceItemSchema::DISCOUNT_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->unsignedBigInteger(OrderInvoiceItemSchema::TAX_CLASS_ID)->nullable();
            $table->string(OrderInvoiceItemSchema::TAX_CLASS_NAME)->nullable();
            $table->decimal(OrderInvoiceItemSchema::TAX_RATE, 7, 4)->unsigned()->default(0.0000);
            $table->decimal(OrderInvoiceItemSchema::TAX_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->decimal(OrderInvoiceItemSchema::LINE_TOTAL, 12, 2)->unsigned()->default(0.00);
            $table->json(OrderInvoiceItemSchema::META)->nullable();
            $table->timestamps();

            $table->index(OrderInvoiceItemSchema::INVOICE_ID);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(OrderInvoiceItemSchema::TABLE);
    }
};
