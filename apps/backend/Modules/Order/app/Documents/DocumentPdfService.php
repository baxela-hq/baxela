<?php

namespace Modules\Order\Documents;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Order\DocumentRendererInterface;
use Modules\Core\Contracts\Gateways\Order\DocumentView;
use Modules\Core\Utils\Locale;
use Modules\Order\Models\OrderCreditNote;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Schemas\OrderInvoiceItem\OrderInvoiceItemSchema;
use Throwable;

/**
 * Owns the D7 artifact lifecycle for both document kinds: claim the
 * generation lease atomically (a ready or in-flight document is a
 * no-op), render through the D12 seam, persist the bytes with a
 * SHA-256 checksum, and never let a render or storage failure touch
 * the immutable record — the row simply lands in generation_failed and
 * a retry reuses the same number and snapshot.
 */
class DocumentPdfService
{
    public function __construct(protected DocumentRendererInterface $renderer) {}

    public function generate(OrderInvoice|OrderCreditNote $document): void
    {
        // Both document tables share the lifecycle column names, so the
        // invoice schema constants name the credit note columns too.
        $claimed = $document::query()
            ->where('id', $document->id)
            ->whereIn(OrderInvoiceSchema::PDF_STATUS, [
                DocumentPdfStatusEnum::PENDING_GENERATION,
                DocumentPdfStatusEnum::GENERATION_FAILED,
            ])
            ->update([
                OrderInvoiceSchema::PDF_STATUS => DocumentPdfStatusEnum::GENERATING->value,
                OrderInvoiceSchema::GENERATION_CLAIMED_AT => now(),
            ]);

        if ($claimed === 0) {
            return; // ready already, or another worker holds the lease
        }

        $document = $document::query()->findOrFail($document->id);

        try {
            $view = $document instanceof OrderInvoice
                ? $this->invoiceView($document)
                : $this->creditNoteView($document);

            $artifact = $this->renderer->render($view);

            $disk = (string) config('order.documents.disk', 'local');
            $path = 'documents/'.$document->number.'.pdf';

            Storage::disk($disk)->put($path, $artifact->contents);

            $document->forceFill([
                OrderInvoiceSchema::PDF_STATUS => DocumentPdfStatusEnum::READY->value,
                OrderInvoiceSchema::PDF_DISK => $disk,
                OrderInvoiceSchema::PDF_PATH => $path,
                OrderInvoiceSchema::PDF_CHECKSUM => hash('sha256', $artifact->contents),
                OrderInvoiceSchema::PDF_GENERATED_AT => now(),
                OrderInvoiceSchema::TEMPLATE_VERSION => $artifact->templateVersion,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            $document->forceFill([
                OrderInvoiceSchema::PDF_STATUS => DocumentPdfStatusEnum::GENERATION_FAILED->value,
            ])->save();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function invoiceItems(OrderInvoice $invoice): array
    {
        return $invoice->items()
            ->get()
            ->map(fn ($item): array => [
                'product_name' => $item->{OrderInvoiceItemSchema::PRODUCT_NAME},
                'sku' => $item->{OrderInvoiceItemSchema::SKU},
                'quantity' => (int) $item->{OrderInvoiceItemSchema::QUANTITY},
                'unit_price' => $item->{OrderInvoiceItemSchema::UNIT_PRICE},
                'discount_amount' => $item->{OrderInvoiceItemSchema::DISCOUNT_AMOUNT},
                'tax_rate' => $item->{OrderInvoiceItemSchema::TAX_RATE},
                'tax_amount' => $item->{OrderInvoiceItemSchema::TAX_AMOUNT},
                'line_total' => $item->{OrderInvoiceItemSchema::LINE_TOTAL},
            ])
            ->all();
    }

    protected function invoiceView(OrderInvoice $invoice): DocumentView
    {
        return new DocumentView(
            kind: 'invoice',
            number: $invoice->number,
            issuedAt: $invoice->issued_at->format('Y-m-d'),
            locale: Locale::fromRequest(),
            currencyCode: (string) $invoice->currency_code,
            decimalPlaces: $this->decimalPlaces((string) $invoice->currency_code),
            pricesIncludeTax: (bool) $invoice->prices_contain_tax,
            seller: $invoice->seller_identity ?? [],
            buyer: $invoice->buyer_identity ?? [],
            billing: $invoice->billing_address ?? [],
            meta: $invoice->meta ?? [],
            items: $this->invoiceItems($invoice),
            totals: [
                'subtotal' => $invoice->subtotal_amount,
                'discount' => $invoice->discount_amount,
                'coupon_code' => $invoice->coupon_code,
                'shipping' => $invoice->shipping_amount,
                'shipping_tax' => $invoice->shipping_tax_amount,
                'tax' => $invoice->tax_amount,
                'total' => $invoice->total_amount,
            ],
        );
    }

    protected function creditNoteView(OrderCreditNote $creditNote): DocumentView
    {
        // The CN renders the invoice's frozen identities (D4/D5): the
        // adjustment belongs to the same legal positions.
        $invoice = $creditNote->invoice()->firstOrFail();

        return new DocumentView(
            kind: 'credit_note',
            number: $creditNote->number,
            issuedAt: $creditNote->issued_at->format('Y-m-d'),
            locale: Locale::fromRequest(),
            currencyCode: (string) $creditNote->currency_code,
            decimalPlaces: $this->decimalPlaces((string) $creditNote->currency_code),
            pricesIncludeTax: false,
            seller: $invoice->seller_identity ?? [],
            buyer: $invoice->buyer_identity ?? [],
            billing: $invoice->billing_address ?? [],
            meta: array_merge($invoice->meta ?? [], ['credit_note' => $creditNote->number]),
            items: [],
            totals: [
                'amount' => $creditNote->amount,
                'reason' => $creditNote->reason,
                'original_number' => $invoice->number,
            ],
        );
    }

    protected function decimalPlaces(string $currencyCode): int
    {
        return (int) (app(CoreGatewayInterface::class)
            ->getActiveCurrencyByCode($currencyCode)
            ?->decimal_places ?? 2);
    }
}
