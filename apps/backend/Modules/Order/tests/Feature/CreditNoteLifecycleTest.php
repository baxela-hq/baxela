<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\Events\Payment\RefundFinalizedEvent;
use Modules\Core\Models\Currency;
use Modules\Core\Support\Money;
use Modules\Order\Actions\Admin\CreditNote\IssueCreditNoteAction;
use Modules\Order\Actions\Admin\Invoice\IssueInvoiceAction;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderCreditNote;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceStatusEnum;
use Modules\Order\Support\DocumentBalanceService;
use Modules\Order\Tests\Feature\HelperTrait;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function cnOrderWithInvoice(float $total = 100.0): array
{
    Storage::fake('local');
    Mail::fake();
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'is_default' => true],
    );

    $order = Order::query()->create([
        OrderSchema::USER_ID => 1,
        OrderSchema::ORDER_CODE => 'CNT'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::PAID_AT => now(),
        OrderSchema::CURRENCY_ID => $currency->id,
        OrderSchema::TOTAL_AMOUNT => $total,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);

    $payment = Payment::query()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => 'manual',
        PaymentSchema::STATUS => PaymentStatusEnum::SUCCESS,
        PaymentSchema::AMOUNT => $total,
        PaymentSchema::CURRENCY_ID => $currency->id,
    ]);

    $invoice = app(IssueInvoiceAction::class)->handle($order->id);

    return [$order, $payment, $invoice];
}

function cnRefund(Payment $payment, float $amount, string $uuid): PaymentRefund
{
    return PaymentRefund::query()->create([
        PaymentRefundSchema::UUID => $uuid,
        PaymentRefundSchema::PAYMENT_ID => $payment->id,
        PaymentRefundSchema::ORDER_ID => $payment->{PaymentSchema::ORDER_ID},
        PaymentRefundSchema::AMOUNT => $amount,
        PaymentRefundSchema::REASON => 'CN lifecycle test',
        PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::SUCCESS,
    ]);
}

it('cuts a credit note from a finalized refund event, against the invoice', function () {
    [$order, $payment, $invoice] = cnOrderWithInvoice(100.0);
    $refund = cnRefund($payment, 40.0, '11111111-1111-4111-8111-111111111111');

    event(RefundFinalizedEvent::fill([
        'payment_id' => $payment->id,
        'order_id' => $order->id,
        'refund_uuid' => $refund->uuid,
        'amount_minor' => Money::fromDecimal('40.00'),
        'currency_code' => 'EUR',
        'reason' => 'CN lifecycle test',
        'locale' => 'en',
    ]));

    $cn = OrderCreditNote::query()->where(OrderCreditNoteSchema::REFUND_UUID, $refund->uuid)->first();

    expect($cn)->not->toBeNull()
        ->and($cn->{OrderCreditNoteSchema::INVOICE_ID})->toBe($invoice->id)
        ->and((float) $cn->{OrderCreditNoteSchema::AMOUNT})->toBe(40.0)
        // PDF artifact lifecycle reached ready
        ->and($cn->pdf_status->value)->toBe('ready');

    // Balances surfaced on the invoice (derived, single calculation site).
    $amounts = app(DocumentBalanceService::class)->amounts($invoice->refresh());
    expect((float) $amounts['credited'])->toBe(40.0)
        ->and((float) $amounts['balance'])->toBe(60.0);
});

it('a duplicate refund event cuts nothing new — unique refund_uuid is the idempotency key', function () {
    [$order, $payment, $invoice] = cnOrderWithInvoice(100.0);
    $refund = cnRefund($payment, 25.0, '22222222-2222-4222-8222-222222222222');

    $dispatch = fn () => event(RefundFinalizedEvent::fill([
        'payment_id' => $payment->id,
        'order_id' => $order->id,
        'refund_uuid' => $refund->uuid,
        'amount_minor' => Money::fromDecimal('25.00'),
        'currency_code' => 'EUR',
        'reason' => 'dup',
        'locale' => 'en',
    ]));

    $dispatch();
    $dispatch();
    $dispatch();

    expect(OrderCreditNote::query()->count())->toBe(1);
});

it('lazily issues the invoice when the refund preceded invoicing', function () {
    Storage::fake('local');
    Mail::fake();
    $currency = Currency::query()->firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'is_default' => true]);

    $order = Order::query()->create([
        OrderSchema::USER_ID => 1,
        OrderSchema::ORDER_CODE => 'CNP'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PARTIALLY_REFUNDED,
        OrderSchema::PAID_AT => now(),
        OrderSchema::CURRENCY_ID => $currency->id,
        OrderSchema::TOTAL_AMOUNT => 80.0,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);

    $payment = Payment::query()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => 'manual',
        PaymentSchema::STATUS => PaymentStatusEnum::SUCCESS,
        PaymentSchema::AMOUNT => 80.0,
        PaymentSchema::CURRENCY_ID => $currency->id,
    ]);

    $refund = cnRefund($payment, 30.0, '33333333-3333-4333-8333-333333333333');

    $cn = app(IssueCreditNoteAction::class)->handle(
        orderId: $order->id,
        refundUuid: $refund->uuid,
        amountMinor: Money::fromDecimal('30.00'),
        reason: 'pre-invoice refund',
    );

    // The lazy-issued invoice exists AND the CN hangs off it.
    expect($cn->{OrderCreditNoteSchema::INVOICE_ID})->not->toBeNull()
        ->and(OrderInvoice::query()->where(OrderInvoiceSchema::ORDER_ID, $order->id)->count())->toBe(1);
});

it('refuses credit notes that would exceed the invoice balance — D14 second line', function () {
    [$order, $payment, $invoice] = cnOrderWithInvoice(100.0);

    // Two 60.00 CNs against a 100.00 invoice: the second must fail.
    $first = cnRefund($payment, 60.0, '44444444-4444-4444-8444-444444444444');
    app(IssueCreditNoteAction::class)->handle($order->id, $first->uuid, Money::fromDecimal('60.00'));

    $second = cnRefund($payment, 60.0, '55555555-5555-4555-8555-555555555555');

    expect(fn () => app(IssueCreditNoteAction::class)->handle($order->id, $second->uuid, Money::fromDecimal('60.00')))
        ->toThrow(OrderException::class);

    expect(OrderCreditNote::query()->count())->toBe(1);
});

it('routes a CN to the replacement invoice when the original is voided (D2 guard)', function () {
    [$order, $payment, $invoice] = cnOrderWithInvoice(100.0);
    $invoice->update([OrderInvoiceSchema::STATUS => OrderInvoiceStatusEnum::VOID]);

    $refund = cnRefund($payment, 10.0, '66666666-6666-4666-8666-666666666666');

    $cn = app(IssueCreditNoteAction::class)->handle($order->id, $refund->uuid, Money::fromDecimal('10.00'));

    // The find-or-issue path cut a fresh revision (the voided one is
    // never the CN target), and the CN hangs off THAT one.
    expect($cn->{OrderCreditNoteSchema::INVOICE_ID})->not->toBe($invoice->id);

    $replacement = OrderInvoice::query()
        ->where(OrderInvoiceSchema::ORDER_ID, $order->id)
        ->where(OrderInvoiceSchema::STATUS, OrderInvoiceStatusEnum::ACTIVE)
        ->first();

    expect($replacement->{OrderInvoiceSchema::REVISION})->toBe(2)
        ->and($cn->{OrderCreditNoteSchema::INVOICE_ID})->toBe($replacement->id);
});

it('reconcile cuts the missing credit note for a finalized refund (D15 crash window)', function () {
    [$order, $payment, $invoice] = cnOrderWithInvoice(90.0);
    $refund = cnRefund($payment, 20.0, '77777777-7777-4777-8777-777777777777');

    // No event ever fired — the crash window.
    expect(OrderCreditNote::query()->count())->toBe(0);

    $this->artisan('order:reconcile-refund-credit-notes')->assertSuccessful();

    $cn = OrderCreditNote::query()->where(OrderCreditNoteSchema::REFUND_UUID, $refund->uuid)->first();
    expect($cn)->not->toBeNull()
        ->and((float) $cn->{OrderCreditNoteSchema::AMOUNT})->toBe(20.0);

    // Idempotent: a second run adds nothing.
    $this->artisan('order:reconcile-refund-credit-notes')->assertSuccessful();
    expect(OrderCreditNote::query()->count())->toBe(1);
});

it('exposes credit notes on the admin surface with download guards', function () {
    [$order, $payment, $invoice] = cnOrderWithInvoice(100.0);

    // Guest hits auth first (before any actingAs — the session persists).
    $this->getJson($this->baseUrl('/admin/credit-notes'))->assertUnauthorized();

    $refund = cnRefund($payment, 15.0, '88888888-8888-4888-8888-888888888888');
    $cn = app(IssueCreditNoteAction::class)->handle($order->id, $refund->uuid, Money::fromDecimal('15.00'));

    $this->actingAs($this->superAdminUser());

    $list = $this->getJson($this->baseUrl('/admin/credit-notes'))->assertOk();
    expect($list->json('data.0.number'))->toBe($cn->number);

    $download = $this->getJson($this->baseUrl("/admin/credit-notes/{$cn->id}/pdf"));
    $download->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($download->streamedContent())->toBe(
        (string) Storage::disk($cn->pdf_disk)->get($cn->pdf_path)
    );
});
