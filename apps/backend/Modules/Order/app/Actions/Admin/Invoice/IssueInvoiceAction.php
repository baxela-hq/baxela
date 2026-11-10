<?php

namespace Modules\Order\Actions\Admin\Invoice;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Order\InvoiceReadyEvent;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Support\Money;
use Modules\Core\Utils\Locale;
use Modules\Order\Documents\DocumentPdfService;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderAddress;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Models\OrderInvoiceItem;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Modules\Order\Schemas\Document\DocumentSequenceTypeEnum;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressTypeEnum;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceStatusEnum;
use Modules\Order\Schemas\OrderInvoiceItem\OrderInvoiceItemSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Support\DocumentNumberService;

class IssueInvoiceAction
{
    /**
     * Issue the order's invoice: allocate the number and freeze the
     * snapshot in one transaction — no PDF, no remote I/O here (D7).
     *
     * D2: the parent-order lock serializes issuance/void/reissue for this
     * order, even when no invoice row exists yet; the (order_id, revision)
     * and number uniques are the final DB-level protection. Idempotent:
     * an already-ACTIVE invoice is returned unchanged, so listener retries
     * and double submits are safe and never allocate a second number.
     *
     * @throws OrderException when the order has no paid state or no currency
     */
    public function handle(int $orderId): OrderInvoice
    {
        $invoice = DB::transaction(function () use ($orderId): OrderInvoice {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            $active = OrderInvoice::query()
                ->where(OrderInvoiceSchema::ORDER_ID, $order->id)
                ->where(OrderInvoiceSchema::STATUS, OrderInvoiceStatusEnum::ACTIVE)
                ->first();

            if ($active !== null) {
                return $active;
            }

            // Every paid state qualifies — partial refunds included, so the
            // lazy-issuance path (refund before invoice) keeps working.
            $paidStates = [
                OrderPaymentStatusEnum::PAID,
                OrderPaymentStatusEnum::PARTIALLY_REFUNDED,
                OrderPaymentStatusEnum::REFUNDED,
            ];
            if (! in_array($order->{OrderSchema::PAYMENT_STATUS}, $paidStates, true)) {
                throw OrderException::invoiceIneligible();
            }

            $currency = app(CoreGatewayInterface::class)->getCurrencyById(
                (int) $order->{OrderSchema::CURRENCY_ID}
            );
            if (is_null($currency)) {
                throw OrderException::invoiceIneligible();
            }

            $revision = (int) OrderInvoice::query()
                ->where(OrderInvoiceSchema::ORDER_ID, $order->id)
                ->max(OrderInvoiceSchema::REVISION) + 1;

            // Frozen snapshot block (D4) — copied once, never re-derived.
            $subtotalMinor = 0;
            $itemPayloads = [];
            foreach ($order->items()->get() as $item) {
                // line_total = the charged per-unit price × quantity, in
                // integer minor units (D11). Tax amounts are COPIED from the
                // order item (D11 v2.2): checkout's finalized rounding is
                // authoritative.
                $lineTotalMinor = Money::fromDecimal(
                    (string) $item->{OrderItemSchema::PRICE_SNAPSHOT}
                ) * (int) $item->{OrderItemSchema::QUANTITY};
                $lineTotal = Money::toDecimal($lineTotalMinor);
                $promotionDiscount = (string) ($item->{OrderItemSchema::PROMOTION_DISCOUNT} ?? '0');

                $itemPayloads[] = [
                    OrderInvoiceItemSchema::PRODUCT_NAME => $item->{OrderItemSchema::PRODUCT_NAME_SNAPSHOT},
                    OrderInvoiceItemSchema::PRODUCT_SLUG => $item->{OrderItemSchema::PRODUCT_SLUG_SNAPSHOT},
                    OrderInvoiceItemSchema::SKU => null,
                    OrderInvoiceItemSchema::VARIANT_ID => $item->{OrderItemSchema::VARIANT_ID},
                    OrderInvoiceItemSchema::QUANTITY => $item->{OrderItemSchema::QUANTITY},
                    OrderInvoiceItemSchema::UNIT_PRICE => $item->{OrderItemSchema::PRICE_SNAPSHOT},
                    OrderInvoiceItemSchema::PROMOTION_DISCOUNT => $promotionDiscount,
                    OrderInvoiceItemSchema::COUPON_DISCOUNT_ALLOCATED => null,
                    OrderInvoiceItemSchema::DISCOUNT_AMOUNT => $promotionDiscount,
                    OrderInvoiceItemSchema::TAX_CLASS_ID => $item->{OrderItemSchema::TAX_CLASS_ID},
                    OrderInvoiceItemSchema::TAX_CLASS_NAME => null,
                    OrderInvoiceItemSchema::TAX_RATE => $item->{OrderItemSchema::TAX_RATE},
                    OrderInvoiceItemSchema::TAX_AMOUNT => $item->{OrderItemSchema::TAX_AMOUNT},
                    OrderInvoiceItemSchema::LINE_TOTAL => $lineTotal,
                    OrderInvoiceItemSchema::META => [
                        'image_url' => $item->{OrderItemSchema::IMAGE_URL},
                        'promotion_id' => $item->{OrderItemSchema::PROMOTION_ID},
                    ],
                ];

                // Tax is exclusive in Baxela's pricing model: price × qty is
                // the goods amount and tax stacks on top — the subtotal is
                // the goods total, never line_total minus tax.
                $subtotalMinor += $lineTotalMinor;
            }

            $buyerEmail = $order->{OrderSchema::USER_ID} !== null
                ? User::query()->find($order->{OrderSchema::USER_ID})?->email
                : null;

            $billingSnapshot = $this->billingAddress((int) $order->id);

            $number = app(DocumentNumberService::class)->next(DocumentSequenceTypeEnum::INVOICE);

            $invoice = OrderInvoice::query()->create([
                OrderInvoiceSchema::ORDER_ID => $order->id,
                OrderInvoiceSchema::REVISION => $revision,
                OrderInvoiceSchema::NUMBER => $number,
                OrderInvoiceSchema::STATUS => OrderInvoiceStatusEnum::ACTIVE,
                OrderInvoiceSchema::ISSUED_AT => now(),
                OrderInvoiceSchema::CURRENCY_CODE => $currency->code,
                OrderInvoiceSchema::SUBTOTAL_AMOUNT => Money::toDecimal($subtotalMinor),
                OrderInvoiceSchema::DISCOUNT_AMOUNT => $order->{OrderSchema::DISCOUNT_AMOUNT},
                OrderInvoiceSchema::COUPON_CODE => $order->{OrderSchema::COUPON_CODE},
                OrderInvoiceSchema::SHIPPING_AMOUNT => $order->{OrderSchema::SHIPPING_COST},
                OrderInvoiceSchema::SHIPPING_TAX_AMOUNT => $order->{OrderSchema::SHIPPING_TAX_AMOUNT},
                OrderInvoiceSchema::TAX_AMOUNT => $order->{OrderSchema::TAX_AMOUNT},
                OrderInvoiceSchema::PRICES_CONTAIN_TAX => (bool) $order->{OrderSchema::PRICES_CONTAIN_TAX},
                OrderInvoiceSchema::TOTAL_AMOUNT => $order->{OrderSchema::TOTAL_AMOUNT},
                OrderInvoiceSchema::BILLING_ADDRESS => $billingSnapshot,
                OrderInvoiceSchema::BUYER_IDENTITY => [
                    'name' => $billingSnapshot[OrderAddressSchema::FULL_NAME] ?? null,
                    'email' => $buyerEmail,
                    // Copied from the FROZEN order snapshot at issuance —
                    // a later registration change can never alter an old
                    // invoice (gap-C1c D4 / the B.1 handoff).
                    'vat_id' => $order->{OrderSchema::BUYER_VAT_ID},
                ],
                OrderInvoiceSchema::SELLER_IDENTITY => [
                    // Frozen at issue time (D4) from the store identity
                    // settings — Setting module's boot applier keeps
                    // config('store.*') current.
                    'name' => (string) config('store.legal_name', config('app.name')),
                    'tax_id' => config('store.tax_id'),
                    'address' => config('store.legal_address'),
                    'contact' => config('store.contact'),
                ],
                OrderInvoiceSchema::META => [
                    'order_code' => $order->{OrderSchema::ORDER_CODE},
                ],
            ]);

            foreach ($itemPayloads as $payload) {
                $payload[OrderInvoiceItemSchema::INVOICE_ID] = $invoice->id;
                OrderInvoiceItem::query()->create($payload);
            }

            return $invoice;
        });

        // D7: generation is separate from issuance — synchronous for v1,
        // after the commit. A render/storage failure lands in
        // generation_failed and a retry reuses the same number and
        // snapshot; re-issuing an already-ready document no-ops here.
        app(DocumentPdfService::class)->generate($invoice);

        $invoice = $invoice->refresh();

        // The email attaches the STORED artifact, so it can only fire
        // once generation actually reached ready (gap-B1 Phase 3).
        if ($invoice->pdf_status === DocumentPdfStatusEnum::READY) {
            $order = $invoice->order()->firstOrFail();

            DB::afterCommit(function () use ($invoice, $order): void {
                event(InvoiceReadyEvent::fill([
                    'invoice_id' => (int) $invoice->id,
                    'user_id' => (int) $order->user_id,
                    'order_code' => (string) $order->order_code,
                    'invoice_number' => (string) $invoice->number,
                    'pdf_disk' => (string) $invoice->pdf_disk,
                    'pdf_path' => (string) $invoice->pdf_path,
                    'locale' => Locale::fromRequest(),
                ]));
            });
        }

        return $invoice;
    }

    /**
     * The billing address snapshot; falls back to the only address when no
     * explicit billing row exists (checkout may store a single address).
     *
     * @return array<string, mixed>
     */
    private function billingAddress(int $orderId): array
    {
        $address = OrderAddress::query()
            ->where(OrderAddressSchema::ORDER_ID, $orderId)
            ->where(OrderAddressSchema::TYPE, OrderAddressTypeEnum::BILLING->value)
            ->first()
            ?? OrderAddress::query()
                ->where(OrderAddressSchema::ORDER_ID, $orderId)
                ->first();

        if (is_null($address)) {
            return [];
        }

        return [
            OrderAddressSchema::FULL_NAME => $address->{OrderAddressSchema::FULL_NAME},
            OrderAddressSchema::PHONE => $address->{OrderAddressSchema::PHONE},
            OrderAddressSchema::ADDRESS_LINE => $address->{OrderAddressSchema::ADDRESS_LINE},
            OrderAddressSchema::CITY => $address->{OrderAddressSchema::CITY},
            OrderAddressSchema::POSTAL_CODE => $address->{OrderAddressSchema::POSTAL_CODE},
            OrderAddressSchema::COUNTRY_CODE => $address->{OrderAddressSchema::COUNTRY_CODE},
        ];
    }
}
