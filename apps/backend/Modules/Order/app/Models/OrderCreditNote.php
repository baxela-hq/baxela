<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteStatusEnum;

/**
 * The accounting adjustment for an issued invoice (D5): references the
 * invoice, optionally the finalized refund that triggered it. refund_uuid
 * is unique when set — the idempotency key of the refund automation.
 *
 * @mixin Builder
 */
class OrderCreditNote extends Model
{
    protected $table = OrderCreditNoteSchema::TABLE;

    protected $fillable = [
        OrderCreditNoteSchema::INVOICE_ID,
        OrderCreditNoteSchema::REFUND_UUID,
        OrderCreditNoteSchema::NUMBER,
        OrderCreditNoteSchema::STATUS,
        OrderCreditNoteSchema::ISSUED_AT,
        OrderCreditNoteSchema::VOIDED_AT,
        OrderCreditNoteSchema::VOID_REASON,
        OrderCreditNoteSchema::CURRENCY_CODE,
        OrderCreditNoteSchema::AMOUNT,
        OrderCreditNoteSchema::REASON,
        OrderCreditNoteSchema::PDF_STATUS,
        OrderCreditNoteSchema::PDF_DISK,
        OrderCreditNoteSchema::PDF_PATH,
        OrderCreditNoteSchema::PDF_CHECKSUM,
        OrderCreditNoteSchema::PDF_GENERATED_AT,
        OrderCreditNoteSchema::TEMPLATE_VERSION,
        OrderCreditNoteSchema::GENERATION_CLAIMED_AT,
    ];

    protected function casts(): array
    {
        return [
            OrderCreditNoteSchema::STATUS => OrderCreditNoteStatusEnum::class,
            OrderCreditNoteSchema::PDF_STATUS => DocumentPdfStatusEnum::class,
            OrderCreditNoteSchema::ISSUED_AT => 'datetime',
            OrderCreditNoteSchema::VOIDED_AT => 'datetime',
            OrderCreditNoteSchema::PDF_GENERATED_AT => 'datetime',
            OrderCreditNoteSchema::GENERATION_CLAIMED_AT => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OrderInvoice::class, OrderCreditNoteSchema::INVOICE_ID);
    }
}
