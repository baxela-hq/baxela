<?php

namespace Modules\Order\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
/**
 * @property int $id
 * @property int $order_id
 * @property int $revision
 * @property string $number
 * @property OrderInvoiceStatusEnum $status
 * @property CarbonInterface $issued_at
 * @property CarbonInterface|null $voided_at
 * @property string|null $void_reason
 * @property string $currency_code
 * @property string $subtotal_amount
 * @property string $discount_amount
 * @property string|null $coupon_code
 * @property string $shipping_amount
 * @property string $shipping_tax_amount
 * @property string $tax_amount
 * @property string $total_amount
 * @property array<string, mixed> $billing_address
 * @property array<string, mixed> $buyer_identity
 * @property array<string, mixed> $seller_identity
 * @property array<string, mixed>|null $meta
 * @property DocumentPdfStatusEnum $pdf_status
 * @property string|null $pdf_disk
 * @property string|null $pdf_path
 * @property string|null $pdf_checksum
 * @property CarbonInterface|null $pdf_generated_at
 * @property string|null $template_version
 * @property CarbonInterface|null $generation_claimed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Order $order
 * @property-read Collection<int, OrderInvoiceItem> $items
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
        OrderInvoiceSchema::PRICES_CONTAIN_TAX,
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, OrderInvoiceSchema::ORDER_ID);
    }

    /** @return HasMany<OrderInvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderInvoiceItem::class, OrderInvoiceItemSchema::INVOICE_ID);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(OrderCreditNote::class, OrderCreditNoteSchema::INVOICE_ID);
    }
}
