<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteStatusEnum;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OrderCreditNoteSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // No cascade (D16): a credit note outlives any attempt to remove
            // the invoice it adjusts.
            $table->foreignId(OrderCreditNoteSchema::INVOICE_ID)
                ->constrained(OrderInvoiceSchema::TABLE)->restrictOnDelete();
            // The finalized refund this note documents; unique when set —
            // the idempotency key for the refund-backed automation (D15).
            $table->string(OrderCreditNoteSchema::REFUND_UUID)->nullable()->unique();
            $table->string(OrderCreditNoteSchema::NUMBER)->unique();
            $table->enum(OrderCreditNoteSchema::STATUS, OrderCreditNoteStatusEnum::cases())
                ->default(OrderCreditNoteStatusEnum::ACTIVE->value);
            $table->timestamp(OrderCreditNoteSchema::ISSUED_AT);
            $table->timestamp(OrderCreditNoteSchema::VOIDED_AT)->nullable();
            $table->string(OrderCreditNoteSchema::VOID_REASON)->nullable();
            $table->string(OrderCreditNoteSchema::CURRENCY_CODE);
            $table->decimal(OrderCreditNoteSchema::AMOUNT, 12, 2)->unsigned()->default(0.00);
            $table->string(OrderCreditNoteSchema::REASON)->nullable();

            $table->enum(OrderCreditNoteSchema::PDF_STATUS, DocumentPdfStatusEnum::cases())
                ->default(DocumentPdfStatusEnum::PENDING_GENERATION->value);
            $table->string(OrderCreditNoteSchema::PDF_DISK)->nullable();
            $table->string(OrderCreditNoteSchema::PDF_PATH)->nullable();
            $table->string(OrderCreditNoteSchema::PDF_CHECKSUM)->nullable();
            $table->timestamp(OrderCreditNoteSchema::PDF_GENERATED_AT)->nullable();
            $table->string(OrderCreditNoteSchema::TEMPLATE_VERSION)->nullable();
            $table->timestamp(OrderCreditNoteSchema::GENERATION_CLAIMED_AT)->nullable();
            $table->timestamps();

            $table->index(OrderCreditNoteSchema::STATUS);
            $table->index(OrderCreditNoteSchema::INVOICE_ID);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(OrderCreditNoteSchema::TABLE);
    }
};
