<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceStatusEnum;
use Modules\Order\Schemas\OrderInvoiceItem\OrderInvoiceItemSchema;

/**
 * The issued financial document for an order (D2: multiple revisions, one
 * ACTIVE at a time). Every money column is a frozen snapshot from issue
 * time (D4) — the row is the legal record; the PDF is a regenerable
 * representation tracked by the pdf_* columns (D7/D16).
 *
 * @mixin Builder
 */
class OrderInvoice extends Model
{
    protected $table = OrderInvoiceSchema::TABLE;

    protected $fillable = [
        OrderInvoiceSchema::ORDER_ID,
        OrderInvoiceSchema::REVISION,
        OrderInvoiceSchema::NUMBER,
        OrderInvoiceSchema::STATUS,
        OrderInvoiceSchema::ISSUED_AT,
        OrderInvoiceSchema::VOIDED_AT,
        OrderInvoiceSchema::VOID_REASON,
        OrderInvoiceSchema::CURRENCY_CODE,
        OrderInvoiceSchema::SUBTOTAL_AMOUNT,
        OrderInvoiceSchema::DISCOUNT_AMOUNT,
        OrderInvoiceSchema::COUPON_CODE,
        OrderInvoiceSchema::SHIPPING_AMOUNT,
        OrderInvoiceSchema::SHIPPING_TAX_AMOUNT,
        OrderInvoiceSchema::TAX_AMOUNT,
        OrderInvoiceSchema::TOTAL_AMOUNT,
        OrderInvoiceSchema::BILLING_ADDRESS,
        OrderInvoiceSchema::BUYER_IDENTITY,
        OrderInvoiceSchema::SELLER_IDENTITY,
        OrderInvoiceSchema::META,
        OrderInvoiceSchema::PDF_STATUS,
        OrderInvoiceSchema::PDF_DISK,
        OrderInvoiceSchema::PDF_PATH,
        OrderInvoiceSchema::PDF_CHECKSUM,
        OrderInvoiceSchema::PDF_GENERATED_AT,
        OrderInvoiceSchema::TEMPLATE_VERSION,
        OrderInvoiceSchema::GENERATION_CLAIMED_AT,
    ];

    protected function casts(): array
    {
        return [
            OrderInvoiceSchema::STATUS => OrderInvoiceStatusEnum::class,
            OrderInvoiceSchema::PDF_STATUS => DocumentPdfStatusEnum::class,
            OrderInvoiceSchema::ISSUED_AT => 'datetime',
            OrderInvoiceSchema::VOIDED_AT => 'datetime',
            OrderInvoiceSchema::PDF_GENERATED_AT => 'datetime',
            OrderInvoiceSchema::GENERATION_CLAIMED_AT => 'datetime',
            OrderInvoiceSchema::BILLING_ADDRESS => 'array',
            OrderInvoiceSchema::BUYER_IDENTITY => 'array',
            OrderInvoiceSchema::SELLER_IDENTITY => 'array',
            OrderInvoiceSchema::META => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, OrderInvoiceSchema::ORDER_ID);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderInvoiceItem::class, OrderInvoiceItemSchema::INVOICE_ID);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(OrderCreditNote::class, OrderCreditNoteSchema::INVOICE_ID);
    }
}
