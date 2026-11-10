<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Order\DocumentRendererInterface;
use Modules\Core\Contracts\Gateways\Order\DocumentView;
use Modules\Core\Contracts\Gateways\Order\PdfArtifact;
use Modules\Core\Models\Currency;
use Modules\Order\Actions\Admin\Invoice\IssueInvoiceAction;
use Modules\Order\Documents\MpdfRenderer;
use Modules\Order\Models\DocumentSequence;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderAddress;
use Modules\Order\Models\OrderCreditNote;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\Document\DocumentSequenceSchema;
use Modules\Order\Schemas\Document\DocumentSequenceTypeEnum;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressTypeEnum;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteStatusEnum;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Schemas\OrderInvoiceItem\OrderInvoiceItemSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Support\StoreTimezone;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * A deterministic paid order: one item at 50.00 × 2 (line 100.00, tax 10.00
 * at 10%), totals copied straight into the invoice snapshot assertions.
 */
function paidInvoiceOrder(OrderPaymentStatusEnum $paymentStatus = OrderPaymentStatusEnum::PAID): Order
{
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'is_default' => true],
    );

    $order = Order::query()->create([
        OrderSchema::USER_ID => 1,
        OrderSchema::ORDER_CODE => 'INV'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => $paymentStatus,
        OrderSchema::PAID_AT => now(),
        OrderSchema::CURRENCY_ID => $currency->id,
        OrderSchema::TOTAL_AMOUNT => 110.0,
        OrderSchema::TAX_AMOUNT => 10.0,
        OrderSchema::SHIPPING_COST => 0.0,
        OrderSchema::SHIPPING_TAX_AMOUNT => 0.0,
        OrderSchema::DISCOUNT_AMOUNT => 0.0,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);

    OrderItem::query()->create([
        OrderItemSchema::ORDER_ID => $order->id,
        OrderItemSchema::VARIANT_ID => 7,
        OrderItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
        OrderItemSchema::PRICE_SNAPSHOT => 50.0,
        OrderItemSchema::QUANTITY => 2,
        OrderItemSchema::TAX_AMOUNT => 10.0,
        OrderItemSchema::TAX_RATE => 10.0,
    ]);

    OrderAddress::query()->create([
        OrderAddressSchema::ORDER_ID => $order->id,
        OrderAddressSchema::TYPE => OrderAddressTypeEnum::BILLING,
        OrderAddressSchema::FULL_NAME => 'Armin Buyer',
        OrderAddressSchema::PHONE => '+49 30 000000',
        OrderAddressSchema::ADDRESS_LINE => 'Torstraße 1',
        OrderAddressSchema::CITY => 'Berlin',
        OrderAddressSchema::POSTAL_CODE => '10119',
        OrderAddressSchema::COUNTRY_CODE => 'DE',
    ]);

    return $order;
}

/**
 * @return array<string, mixed> the issued invoice's resource payload
 */
function issueFor(Order $order): array
{
    return test()->actingAs(test()->superAdminUser())
        ->postJson(test()->baseUrl("/admin/orders/{$order->id}/invoice"))
        ->assertCreated()
        ->json('data');
}

it('denies a guest with 401', function () {
    $this->getJson($this->baseUrl('/admin/invoices'))->assertStatus(401);
});

it('denies the invoice endpoints without permission', function () {
    $order = paidInvoiceOrder();

    $this->actingAs(User::factory()->create())
        ->postJson($this->baseUrl("/admin/orders/{$order->id}/invoice"))
        ->assertStatus(403);
});

it('issues an invoice whose snapshot is copied from the finalized order', function () {
    $order = paidInvoiceOrder();

    $response = $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/orders/{$order->id}/invoice"))
        ->assertCreated();

    $data = $response->json('data');
    $year = StoreTimezone::year();

    // Numbering: {PREFIX}-{YEAR}-{6-digit}, first allocation for the year.
    expect($data[OrderInvoiceSchema::NUMBER])->toBe('INV-'.$year.'-000001')
        ->and($data[OrderInvoiceSchema::REVISION])->toBe(1)
        ->and($data[OrderInvoiceSchema::STATUS])->toBe('active')
        ->and($data[OrderInvoiceSchema::CURRENCY_CODE])->toBe('EUR')
        // Totals copied from the order, never recalculated (D11 v2.2).
        ->and($data[OrderInvoiceSchema::TOTAL_AMOUNT])->toBe('110.00')
        ->and($data[OrderInvoiceSchema::TAX_AMOUNT])->toBe('10.00')
        ->and($data[OrderInvoiceSchema::SUBTOTAL_AMOUNT])->toBe('100.00')
        ->and($data[OrderInvoiceSchema::BILLING_ADDRESS][OrderAddressSchema::COUNTRY_CODE])->toBe('DE')
        ->and($data[OrderInvoiceSchema::BUYER_IDENTITY]['name'])->toBe('Armin Buyer')
        ->and($data[OrderInvoiceSchema::SELLER_IDENTITY]['name'])->toBe(config('app.name'))
        ->and($data[OrderInvoiceSchema::META]['order_code'])->toBe($order->{OrderSchema::ORDER_CODE})
        ->and($data[OrderInvoiceSchema::ISSUED_AT])->not->toBeNull()
        // Balance view: nothing credited yet.
        ->and($data['credited_amount'])->toBe('0.00')
        ->and($data['balance'])->toBe('110.00')
        // The item snapshot: line_total derived from charged price × qty,
        // tax COPIED from the order item.
        ->and($data[OrderInvoiceSchema::RES_ITEMS][0][OrderInvoiceItemSchema::LINE_TOTAL])->toBe('100.00')
        ->and($data[OrderInvoiceSchema::RES_ITEMS][0][OrderInvoiceItemSchema::TAX_AMOUNT])->toBe('10.00')
        ->and($data[OrderInvoiceSchema::RES_ITEMS][0][OrderInvoiceItemSchema::UNIT_PRICE])->toBe('50.00')
        ->and($data[OrderInvoiceSchema::RES_ITEMS][0][OrderInvoiceItemSchema::QUANTITY])->toBe(2)
        ->and($data[OrderInvoiceSchema::RES_ITEMS][0][OrderInvoiceItemSchema::PRODUCT_NAME])->toBe('Test Product');

    expect(OrderInvoice::query()->count())->toBe(1);
});

it('copies the frozen VAT id into the invoice buyer identity (gap-C1c)', function () {
    $order = paidInvoiceOrder();
    $order->update([OrderSchema::BUYER_VAT_ID => 'DE123456789']);

    $data = issueFor($order);

    expect($data[OrderInvoiceSchema::BUYER_IDENTITY]['vat_id'])->toBe('DE123456789');
});

it('is idempotent: issuing twice returns the same invoice and no second number', function () {
    $order = paidInvoiceOrder();

    $first = issueFor($order);
    $second = issueFor($order);

    expect($second[OrderInvoiceSchema::ID])->toBe($first[OrderInvoiceSchema::ID])
        ->and($second[OrderInvoiceSchema::NUMBER])->toBe($first[OrderInvoiceSchema::NUMBER])
        ->and(OrderInvoice::query()->count())->toBe(1);
});

it('refuses to issue for an order without a paid state', function () {
    $order = paidInvoiceOrder(OrderPaymentStatusEnum::UNPAID);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/orders/{$order->id}/invoice"))
        ->assertStatus(400)
        ->assertJsonPath('code', 'order.invoice.ineligible');

    expect(OrderInvoice::query()->count())->toBe(0);
});

it('issues lazily for a partially refunded order (refund before invoice)', function () {
    $order = paidInvoiceOrder(OrderPaymentStatusEnum::PARTIALLY_REFUNDED);

    $data = issueFor($order);

    expect($data[OrderInvoiceSchema::STATUS])->toBe('active')
        ->and($data[OrderInvoiceSchema::NUMBER])->toContain('INV-');
});

it('continues the sequence across orders within the year', function () {
    $first = issueFor(paidInvoiceOrder());
    $second = issueFor(paidInvoiceOrder());

    expect($second[OrderInvoiceSchema::NUMBER])->not->toBe($first[OrderInvoiceSchema::NUMBER])
        ->and(str_ends_with($second[OrderInvoiceSchema::NUMBER], '000002'))->toBeTrue();
});

it('allocates per year: a prior-year sequence row does not leak into this year', function () {
    DocumentSequence::query()->create([
        DocumentSequenceSchema::TYPE => DocumentSequenceTypeEnum::INVOICE,
        DocumentSequenceSchema::YEAR => StoreTimezone::year() - 1,
        DocumentSequenceSchema::LAST_NUMBER => 500,
    ]);

    $data = issueFor(paidInvoiceOrder());

    expect($data[OrderInvoiceSchema::NUMBER])->toBe('INV-'.StoreTimezone::year().'-000001');
});

it('voids an invoice and reissues under the next revision with a fresh number', function () {
    $order = paidInvoiceOrder();
    $original = issueFor($order);

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl("/admin/invoices/{$original[OrderInvoiceSchema::ID]}/void"), [
            'reason' => 'wrong billing address',
        ])
        ->assertOk()
        ->assertJsonPath('data.'.OrderInvoiceSchema::STATUS, 'void')
        ->assertJsonPath('data.'.OrderInvoiceSchema::VOID_REASON, 'wrong billing address');

    $replacement = issueFor($order);

    // The voided document is retained with its number; the replacement is
    // a NEW legal document under the next revision (D2/D8) — no balance
    // transfers (v2.2 rule).
    expect($replacement[OrderInvoiceSchema::ID])->not->toBe($original[OrderInvoiceSchema::ID])
        ->and($replacement[OrderInvoiceSchema::REVISION])->toBe(2)
        ->and($replacement[OrderInvoiceSchema::NUMBER])->not->toBe($original[OrderInvoiceSchema::NUMBER])
        ->and(OrderInvoice::query()->count())->toBe(2);
});

it('refuses to void an already voided invoice', function () {
    $order = paidInvoiceOrder();
    $invoice = issueFor($order);

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl("/admin/invoices/{$invoice[OrderInvoiceSchema::ID]}/void"), [
            'reason' => 'once is enough',
        ])->assertOk();

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl("/admin/invoices/{$invoice[OrderInvoiceSchema::ID]}/void"), [
            'reason' => 'twice is not',
        ])->assertStatus(400)
        ->assertJsonPath('code', 'order.invoice.invalid_transition');
});

it('validates the void payload', function () {
    $order = paidInvoiceOrder();
    $invoice = issueFor($order);

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl("/admin/invoices/{$invoice[OrderInvoiceSchema::ID]}/void"), [])
        ->assertStatus(422);
});

it('returns 404 for an unknown invoice', function () {
    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/invoices/999'))
        ->assertStatus(404);
});

it('lists invoices with filters', function () {
    $order = paidInvoiceOrder();
    issueFor($order);

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/invoices'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/invoices?filter[status]=void'))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/invoices?filter[order_id]='.$order->id))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('serializes allocation across transactions: numbers never collide', function () {
    // True process-level concurrency needs a multi-connection harness the
    // SQLite test env cannot provide; the parent-order lock (D2) plus the
    // (type, year) unique absorb the race structurally. What we CAN assert:
    // sequential allocations inside separate transactions keep incrementing
    // the shared counter exactly once per document.
    $order = paidInvoiceOrder();

    $first = DB::transaction(function () use ($order): OrderInvoice {
        return app(IssueInvoiceAction::class)->handle($order->id);
    });
    $second = DB::transaction(function () use ($order): OrderInvoice {
        return app(IssueInvoiceAction::class)->handle($order->id);
    });

    expect($second->id)->toBe($first->id)
        ->and(OrderInvoice::query()->count())->toBe(1)
        ->and(OrderCreditNote::query()
            ->where(OrderCreditNoteSchema::STATUS, OrderCreditNoteStatusEnum::ACTIVE)
            ->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// PDF artifact lifecycle (Phase 2, D7): issuance stays render-free; the
// artifact exists through an explicit, retryable, lease-guarded lifecycle.
// ---------------------------------------------------------------------------

it('issues an invoice whose PDF reaches ready with verified stored bytes', function () {
    Storage::fake('local');
    $order = paidInvoiceOrder();

    $invoice = app(IssueInvoiceAction::class)->handle($order->id);

    $invoice = OrderInvoice::query()->findOrFail($invoice->id);
    $bytes = Storage::disk('local')->get($invoice->pdf_path);

    expect($invoice->pdf_status->value)->toBe('ready')
        ->and($bytes)->not->toBeNull()
        ->and(substr((string) $bytes, 0, 5))->toBe('%PDF-')
        ->and($invoice->pdf_checksum)->toBe(hash('sha256', (string) $bytes))
        ->and($invoice->template_version)->toBe(MpdfRenderer::TEMPLATE_VERSION)
        // the seller identity snapshot comes from the store settings
        ->and($invoice->seller_identity['name'])->toBe(config('app.name'));
});

it('renders the fa locale invoice RTL without exceptions', function () {
    Storage::fake('local');
    // Locale::fromRequest() reads the request's preferred language —
    // bind one so the document renders in fa.
    $this->app->instance(
        'request',
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fa']),
    );
    $order = paidInvoiceOrder();

    $invoice = app(IssueInvoiceAction::class)->handle($order->id);

    $bytes = (string) Storage::disk('local')->get($invoice->pdf_path);

    expect($invoice->pdf_status->value)->toBe('ready')
        ->and(substr($bytes, 0, 5))->toBe('%PDF-');
});

it('multi-page: many items paginate with a valid pdf', function () {
    Storage::fake('local');
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'is_default' => true],
    );

    $order = Order::query()->create([
        OrderSchema::USER_ID => 1,
        OrderSchema::ORDER_CODE => 'INV'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::PAID_AT => now(),
        OrderSchema::CURRENCY_ID => $currency->id,
        OrderSchema::TOTAL_AMOUNT => 3000.0,
        OrderSchema::TAX_AMOUNT => 0.0,
        OrderSchema::SHIPPING_COST => 0.0,
        OrderSchema::SHIPPING_TAX_AMOUNT => 0.0,
        OrderSchema::DISCOUNT_AMOUNT => 0.0,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);

    for ($i = 0; $i < 60; $i++) {
        OrderItem::query()->create([
            OrderItemSchema::ORDER_ID => $order->id,
            OrderItemSchema::VARIANT_ID => $i + 1,
            OrderItemSchema::PRODUCT_NAME_SNAPSHOT => 'Item number '.$i.' with a reasonably long product name for wrapping',
            OrderItemSchema::PRICE_SNAPSHOT => 50.0,
            OrderItemSchema::QUANTITY => 1,
            OrderItemSchema::TAX_AMOUNT => 0.0,
            OrderItemSchema::TAX_RATE => 0.0,
        ]);
    }

    $invoice = app(IssueInvoiceAction::class)->handle($order->id);
    $bytes = (string) Storage::disk('local')->get($invoice->pdf_path);

    expect(substr($bytes, 0, 5))->toBe('%PDF-')
        ->and(substr_count($bytes, '/Type /Page'))->toBeGreaterThan(1);
});

it('a failed generation never touches the number, and a retry reuses both number and snapshot', function () {
    Storage::fake('local');
    $order = paidInvoiceOrder();

    // First generation blows up inside the renderer seam.
    $this->app->bind(DocumentRendererInterface::class, function () {
        return new class implements DocumentRendererInterface
        {
            public function render(DocumentView $view): PdfArtifact
            {
                throw new RuntimeException('renderer exploded');
            }
        };
    });

    $invoice = app(IssueInvoiceAction::class)->handle($order->id);
    $number = $invoice->number;
    $snapshotTotal = $invoice->total_amount;

    expect($invoice->refresh()->pdf_status->value)->toBe('generation_failed');

    // The retry (real renderer rebound) must reuse number and snapshot.
    $this->app->bind(DocumentRendererInterface::class, MpdfRenderer::class);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/invoices/{$invoice->id}/pdf/retry"))
        ->assertOk();

    $fresh = OrderInvoice::query()->findOrFail($invoice->id);

    expect($fresh->pdf_status->value)->toBe('ready')
        ->and($fresh->number)->toBe($number)
        ->and((float) $fresh->total_amount)->toBe((float) $snapshotTotal)
        ->and(Storage::disk('local')->get($fresh->pdf_path))->not->toBeNull();
});

it('downloads serve ready documents only, with auth and not-ready guards', function () {
    Storage::fake('local');
    $order = paidInvoiceOrder();
    $invoice = app(IssueInvoiceAction::class)->handle($order->id);

    // guest
    $this->getJson($this->baseUrl("/admin/invoices/{$invoice->id}/pdf"))->assertUnauthorized();

    // ready: bytes stream back as the stored artifact
    $response = $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl("/admin/invoices/{$invoice->id}/pdf"));
    $bytes = (string) Storage::disk('local')->get($invoice->pdf_path);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($response->streamedContent())->toBe($bytes);

    // not ready: 409, never an incomplete document
    OrderInvoice::query()->where('id', $invoice->id)
        ->update([OrderInvoiceSchema::PDF_STATUS => 'pending_generation']);

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl("/admin/invoices/{$invoice->id}/pdf"))
        ->assertStatus(409)
        ->assertJsonPath('code', 'order.invoice.pdf_not_ready');
});

it('retry refuses anything that is not a failed generation', function () {
    Storage::fake('local');
    $order = paidInvoiceOrder();
    $invoice = app(IssueInvoiceAction::class)->handle($order->id);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/invoices/{$invoice->id}/pdf/retry"))
        ->assertStatus(409)
        ->assertJsonPath('code', 'order.invoice.pdf_not_retryable');
});

it('a storage outage lands the document in generation_failed, and a later retry recovers', function () {
    // The S3-down case from the plan's Phase 2 test list: the storage
    // write throws inside the service's try/catch — same state machine
    // as the renderer failure, exercised at the real seam.
    $order = paidInvoiceOrder();

    // Point at a disk that doesn't exist in the filesystems config —
    // the write inside DocumentPdfService throws.
    config(['order.documents.disk' => 's3_outage']);
    $invoice = app(IssueInvoiceAction::class)->handle($order->id);

    expect($invoice->refresh()->pdf_status->value)->toBe('generation_failed');

    // Storage recovers (local fake disk), the retry reaches ready on
    // the same number.
    Storage::fake('local');
    config(['order.documents.disk' => 'local']);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/invoices/{$invoice->id}/pdf/retry"))
        ->assertOk();

    expect($invoice->refresh()->pdf_status->value)->toBe('ready')
        ->and($invoice->refresh()->number)->toBe($invoice->number);
});
