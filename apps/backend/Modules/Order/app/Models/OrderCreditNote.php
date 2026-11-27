<?php

namespace Modules\Order\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteStatusEnum;

/**
 * The accounting adjustment for an issued invoice (D5): references the
 * invoice, optionally the finalized refund that triggered it. refund_uuid
 * is unique when set — the idempotency key of the refund automation.
 *
 * @mixin Builder<OrderCreditNote>
 */
/**
 * @property int $id
 * @property int $invoice_id
 * @property string|null $refund_uuid
 * @property string $number
 * @property OrderCreditNoteStatusEnum $status
 * @property CarbonInterface $issued_at
 * @property CarbonInterface|null $voided_at
 * @property string|null $void_reason
 * @property string $currency_code
 * @property string|null $exchange_rate
 * @property string $amount
 * @property DocumentPdfStatusEnum $pdf_status
 * @property string|null $pdf_disk
 * @property string|null $pdf_path
 * @property string|null $pdf_checksum
 * @property CarbonInterface|null $pdf_generated_at
 * @property string|null $template_version
 * @property CarbonInterface|null $generation_claimed_at
 * @property-read OrderInvoice $invoice
 */
class OrderCreditNote extends Model
{
    use TenantScopedTrait;

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
        OrderCreditNoteSchema::EXCHANGE_RATE,
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
            OrderCreditNoteSchema::EXCHANGE_RATE => 'decimal:8',
            OrderCreditNoteSchema::GENERATION_CLAIMED_AT => 'datetime',
        ];
    }

    /** @return BelongsTo<OrderInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OrderInvoice::class, OrderCreditNoteSchema::INVOICE_ID);
    }
}
