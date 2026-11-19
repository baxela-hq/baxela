<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Events\Order\OrderRefundedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentFailedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentSucceededEvent;
use Modules\Core\Models\Currency;
use Modules\Core\Support\Money;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Support\AmountFormatter;
use Modules\Notification\Tests\Feature\HelperTrait;
use Modules\Order\Models\Order;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * The standard fixture: USD base (symbol left), EUR active at 0.92
 * (symbol right) — 150.50 × 0.92 = 138.46 exactly.
 */
function seedAmountCurrencies(): void
{
    Currency::factory()->create([
        'code' => 'USD', 'symbol' => '$', 'is_symbol_right' => false,
        'decimal_places' => 2, 'exchange_rate' => '1.00000000',
        'is_default' => true, 'is_active' => true,
    ]);
    Currency::factory()->create([
        'code' => 'EUR', 'symbol' => '€', 'is_symbol_right' => true,
        'decimal_places' => 2, 'exchange_rate' => '0.92000000',
        'is_default' => false, 'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function amountEventPayload(int $userId, array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'user_id' => $userId,
        'order_code' => 'ORD-AMT',
        'status' => 'pending',
        'total_amount' => 150.50,
        'expires_at' => now()->addMinutes(30)->toDateTimeString(),
        'created_at' => now()->toDateTimeString(),
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Formatter — precedence rows 1-5
// ---------------------------------------------------------------------------

it('renders the snapshot currency with its symbol when the display row exists (row 1)', function () {
    seedAmountCurrencies();
    $usd = Currency::query()->where('code', 'USD')->first();
    $formatter = app(AmountFormatter::class);

    // Symbol-left display currency.
    expect($formatter->forCustomer(150.50, 'USD', 'EUR', '0.92000000'))->toBe('138.46 €')
        // Symbol-right placement mirrored on a left-side currency.
        ->and($formatter->forCustomer(150.50, 'EUR', 'USD', '1.08695652'))->toBe('$163.59')
        // Charge view never display-converts: base + ISO code.
        ->and($formatter->forCharge(150.50, 'usd'))->toBe('150.50 USD');
});

it('resolves display metadata for a currency deactivated after checkout (row 1, inactive)', function () {
    seedAmountCurrencies();
    Currency::factory()->create([
        'code' => 'IRR', 'symbol' => '﷼', 'is_symbol_right' => true,
        'decimal_places' => 0, 'exchange_rate' => '600000.00000000',
        'is_default' => false, 'is_active' => false,
    ]);

    // 150.50 × 600000 = 90,300,000 — zero decimals preserved, symbol from
    // the inactive row, never ".00".
    expect(app(AmountFormatter::class)->forCustomer(150.50, 'USD', 'IRR', '600000.00000000'))
        ->toBe('90,300,000 ﷼');
});

it('falls back to the ISO code at storage precision when the display row was deleted (row 2)', function () {
    seedAmountCurrencies();

    // 'XYZ' has no row: conversion still uses the snapshot rate; precision
    // degrades to the fixed storage 2 — documented non-exact rendering.
    expect(app(AmountFormatter::class)->forCustomer(150.50, 'USD', 'XYZ', '0.92000000'))
        ->toBe('138.46 XYZ');
});

it('treats a snapshot code without a rate as base (row 3)', function () {
    seedAmountCurrencies();

    expect(app(AmountFormatter::class)->forCustomer(150.50, 'USD', 'EUR', null))
        ->toBe('150.50 USD');
});

it('renders base plus code without a snapshot (row 4)', function () {
    seedAmountCurrencies();

    expect(app(AmountFormatter::class)->forCustomer(150.50, 'USD', null, null))
        ->toBe('150.50 USD');
});

it('renders a bare number with a warning when no base is resolvable (row 5)', function () {
    $formatter = app(AmountFormatter::class);
    Log::shouldReceive('warning')->twice();

    // 5a: legacy event (no id) on a store with no default currency row.
    expect($formatter->baseForOrder(null))->toBeNull();

    // 5b: a known transaction-time id whose row cannot be resolved — the
    // current default (seeded next) is never substituted for it.
    Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    expect($formatter->baseForOrder(424242))->toBeNull();

    // And the bare rendering itself: grouped, two decimals, no label.
    expect($formatter->forCharge(150.506, null))->toBe('150.51');
});

// ---------------------------------------------------------------------------
// Formatter — rounding contract and precision
// ---------------------------------------------------------------------------

it('rounds half-up exactly once, at the conversion boundary', function () {
    seedAmountCurrencies();
    Currency::factory()->create([
        'code' => 'PCT', 'symbol' => null, 'decimal_places' => 2,
        'is_default' => false, 'is_active' => true,
    ]);
    $formatter = app(AmountFormatter::class);

    // 12.50 × 0.05 = 0.625 → half-up to 0.63 (both the minor units and
    // the rendered string — the string guard catches any second rounding
    // a formatter might introduce during grouping).
    expect(Money::convert(1250, '0.05000000', 2, 2))->toBe(63)
        ->and($formatter->forCustomer(12.50, 'USD', 'PCT', '0.05000000'))->toBe('0.63 PCT')
        // One minor below the half boundary rounds down…
        ->and(Money::convert(1249, '0.05000000', 2, 2))->toBe(62)
        ->and($formatter->forCustomer(12.49, 'USD', 'PCT', '0.05000000'))->toBe('0.62 PCT')
        // …one above rounds up.
        ->and(Money::convert(1251, '0.05000000', 2, 2))->toBe(63)
        ->and($formatter->forCustomer(12.51, 'USD', 'PCT', '0.05000000'))->toBe('0.63 PCT');
});

it('honors three-decimal display currencies', function () {
    seedAmountCurrencies();
    Currency::factory()->create([
        'code' => 'KWD', 'symbol' => 'د.ك', 'is_symbol_right' => true,
        'decimal_places' => 3, 'exchange_rate' => '0.03100000',
        'is_default' => false, 'is_active' => true,
    ]);

    // 150.50 × 0.031 = 4.6655 → 3-dp half-up → 4.666.
    expect(app(AmountFormatter::class)->forCustomer(150.50, 'USD', 'KWD', '0.03100000'))
        ->toBe('4.666 د.ك');
});

it('round-trips the decimal(12,2) ceiling exactly through the parse boundary', function () {
    seedAmountCurrencies();

    // 9,999,999,999.99 is the largest decimal(12,2) value: %.2F must
    // reproduce it digit-for-digit (never 10,000,000,000.00).
    expect(app(AmountFormatter::class)->forCharge(9999999999.99, 'USD'))
        ->toBe('9,999,999,999.99 USD');
});

it('degrades to base when the snapshot rate is malformed', function () {
    seedAmountCurrencies();
    Log::shouldReceive('warning')->once();

    // Only possible on hand-built legacy payloads — the notification must
    // never fatal over presentation.
    expect(app(AmountFormatter::class)->forCustomer(150.50, 'USD', 'EUR', 'not-a-rate'))
        ->toBe('150.50 USD');
});

// ---------------------------------------------------------------------------
// The seven listeners — exact strings in database and email content
// ---------------------------------------------------------------------------

it('shows the checkout currency to the customer and the base to the admin on order creation', function () {
    Mail::fake();
    seedAmountCurrencies();
    $usd = Currency::query()->where('code', 'USD')->first();

    $admin = $this->superAdminUser();
    $customer = User::factory()->create();

    event(OrderCreatedEvent::fill(amountEventPayload($customer->id, [
        'currency_id' => $usd->id,
        'display_currency_code' => 'EUR',
        'display_fx_rate' => '0.92000000',
    ])));

    // Customer: the display string in BOTH channels.
    expect(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_CREATED->value)
            ->where('audience', 'user')
            ->first()->body
    )->toContain('138.46 €');
    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail) => str_contains($mail->body, '138.46 €'));

    // Admin: base + ISO code — money actually charged.
    expect(
        Notification::query()
            ->where('user_id', $admin->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_CREATED->value)
            ->where('audience', 'admin')
            ->first()->body
    )->toContain('150.50 USD');
});

it('falls back to base plus code on legacy order-created events without a snapshot', function () {
    Mail::fake();
    seedAmountCurrencies();
    $usd = Currency::query()->where('code', 'USD')->first();

    $customer = User::factory()->create();
    event(OrderCreatedEvent::fill(amountEventPayload($customer->id, [
        'currency_id' => $usd->id,
        // no display snapshot — base rendering
    ])));

    expect(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_CREATED->value)
            ->first()->body
    )->toContain('150.50 USD');
});

it('shows the checkout currency on the order-paid and order-refunded notifications', function () {
    Mail::fake();
    seedAmountCurrencies();
    $usd = Currency::query()->where('code', 'USD')->first();

    $customer = User::factory()->create();
    $snapshot = [
        'currency_id' => $usd->id,
        'display_currency_code' => 'EUR',
        'display_fx_rate' => '0.92000000',
    ];

    event(OrderPaidEvent::fill(amountEventPayload($customer->id, $snapshot) + [
        'payment_status' => 'paid',
    ]));
    event(OrderRefundedEvent::fill(amountEventPayload($customer->id, $snapshot) + [
        'payment_status' => 'refunded',
    ]));

    expect(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_PAID->value)
            ->first()->body
    )->toContain('138.46 €')->and(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_REFUNDED->value)
            ->first()->body
    )->toContain('138.46 €');
});

it('labels payment notifications with the order base currency, not a display currency', function () {
    Mail::fake();
    seedAmountCurrencies();
    $usd = Currency::query()->where('code', 'USD')->first();

    $admin = $this->superAdminUser();
    $customer = User::factory()->create();
    // Payment amount deliberately distinct from every order total above —
    // guards the amount-source wiring (amount, never total_amount).
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'order_code' => 'ORD-PAY',
        'currency_id' => $usd->id,
        'display_currency_code' => 'EUR',
        'display_fx_rate' => '0.92000000',
    ]);

    event(PaymentSucceededEvent::fill([
        'id' => 1, 'order_id' => $order->id, 'amount' => 99.99, 'status' => 'success',
    ]));
    event(PaymentFailedEvent::fill([
        'id' => 2, 'order_id' => $order->id, 'amount' => 77.77, 'status' => 'failed',
    ]));

    // Charges describe money that actually moved: base + code, never the
    // order's EUR snapshot — 99.99 × 0.92 would be 91.99, which must NOT appear.
    $succeededBody = Notification::query()
        ->where('user_id', $admin->id)
        ->where('code', NotificationCodeEnum::PAYMENT_PAYMENT_SUCCEEDED->value)
        ->first()->body;
    expect($succeededBody)->toContain('99.99 USD');
    expect(str_contains($succeededBody, '91.99'))->toBeFalse();
    expect(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::PAYMENT_PAYMENT_FAILED->value)
            ->first()->body
    )->toContain('77.77 USD')->and(
        Notification::query()
            ->where('user_id', $admin->id)
            ->where('code', NotificationCodeEnum::PAYMENT_PAYMENT_FAILED->value)
            ->first()->body
    )->toContain('77.77 USD');
});

it('renders the decimal(12,2) ceiling exactly through the real event-to-listener path', function () {
    Mail::fake();
    seedAmountCurrencies();
    $usd = Currency::query()->where('code', 'USD')->first();

    $admin = $this->superAdminUser();
    $customer = User::factory()->create();

    event(OrderCreatedEvent::fill(amountEventPayload($customer->id, [
        'total_amount' => 9999999999.99,
        'currency_id' => $usd->id,
        'display_currency_code' => 'EUR',
        'display_fx_rate' => '0.92000000',
    ])));

    // Parse boundary through the listener: 9,999,999,999.99 stays exact,
    // converts with integer math to 9,199,999,999.99 (one half-up round).
    expect(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_CREATED->value)
            ->first()->body
    )->toContain('9,199,999,999.99 €')->and(
        Notification::query()
            ->where('user_id', $admin->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_CREATED->value)
            ->first()->body
    )->toContain('9,999,999,999.99 USD');
});
