<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OrderInvoiceSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // Financial-record protection (D16): no cascade. Deleting an
            // order that carries issued documents must fail loudly, never
            // silently destroy the books.
            $table->foreignId(OrderInvoiceSchema::ORDER_ID)
                ->constrained(OrderSchema::TABLE)->restrictOnDelete();
            $table->unsignedInteger(OrderInvoiceSchema::REVISION)->default(1);
            $table->string(OrderInvoiceSchema::NUMBER)->unique();
            $table->enum(OrderInvoiceSchema::STATUS, OrderInvoiceStatusEnum::cases())
                ->default(OrderInvoiceStatusEnum::ACTIVE->value);
            $table->timestamp(OrderInvoiceSchema::ISSUED_AT);
            $table->timestamp(OrderInvoiceSchema::VOIDED_AT)->nullable();
            $table->string(OrderInvoiceSchema::VOID_REASON)->nullable();

            // Frozen snapshot block (D4): copied at issue time, never
            // re-derived from live order rows or current settings.
            $table->string(OrderInvoiceSchema::CURRENCY_CODE);
            $table->decimal(OrderInvoiceSchema::SUBTOTAL_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->decimal(OrderInvoiceSchema::DISCOUNT_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->string(OrderInvoiceSchema::COUPON_CODE)->nullable();
            $table->decimal(OrderInvoiceSchema::SHIPPING_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->decimal(OrderInvoiceSchema::SHIPPING_TAX_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->decimal(OrderInvoiceSchema::TAX_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->decimal(OrderInvoiceSchema::TOTAL_AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->json(OrderInvoiceSchema::BILLING_ADDRESS);
            $table->json(OrderInvoiceSchema::BUYER_IDENTITY);
            $table->json(OrderInvoiceSchema::SELLER_IDENTITY);
            $table->json(OrderInvoiceSchema::META)->nullable();

            // PDF artifact lifecycle (D7): the legal record is the row;
            // the PDF is a regenerable representation. The generating claim
            // carries a lease so a crashed worker cannot wedge the document.
            $table->enum(OrderInvoiceSchema::PDF_STATUS, DocumentPdfStatusEnum::cases())
                ->default(DocumentPdfStatusEnum::PENDING_GENERATION->value);
            $table->string(OrderInvoiceSchema::PDF_DISK)->nullable();
            $table->string(OrderInvoiceSchema::PDF_PATH)->nullable();
            $table->string(OrderInvoiceSchema::PDF_CHECKSUM)->nullable();
            $table->timestamp(OrderInvoiceSchema::PDF_GENERATED_AT)->nullable();
            $table->string(OrderInvoiceSchema::TEMPLATE_VERSION)->nullable();
            $table->timestamp(OrderInvoiceSchema::GENERATION_CLAIMED_AT)->nullable();
            $table->timestamps();

            // One ACTIVE invoice per order at a time; every revision is
            // retained for audit (D2).
            $table->unique([OrderInvoiceSchema::ORDER_ID, OrderInvoiceSchema::REVISION]);
            $table->index(OrderInvoiceSchema::STATUS);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(OrderInvoiceSchema::TABLE);
    }
};
