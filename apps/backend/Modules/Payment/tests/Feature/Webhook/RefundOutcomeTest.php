<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentRefundReversedEvent;
use Modules\Core\Database\Factories\CurrencyFactory;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;
use Modules\Payment\Tests\Feature\Webhook\TestWebhookDriver;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    config(['payment.drivers.test' => TestWebhookDriver::class]);
});

/**
 * A paid order with a settled gateway payment. The webhook stand-in
 * ('test' driver) routes refund outcomes; the payment method itself is
 * irrelevant to the confirm path.
 */
function paidGatewayOrder(float $amount = 100.0, array $paymentOverrides = []): array
{
    $order = Order::query()->create([
        OrderSchema::USER_ID => 1,
        OrderSchema::ORDER_CODE => 'RO'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::PAID_AT => now(),
        OrderSchema::TOTAL_AMOUNT => $amount,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);

    $payment = Payment::query()->create(array_merge([
        PaymentSchema::ORDER_ID => $order->{OrderSchema::ID},
        PaymentSchema::METHOD => PaymentMethodEnum::MANUAL,
        PaymentSchema::STATUS => PaymentStatusEnum::SUCCESS,
        PaymentSchema::AMOUNT => $amount,
        PaymentSchema::TRANSACTION_ID => 'gw-'.uniqid(),
        PaymentSchema::GATEWAY_TRANSACTION_ID => 'psp-'.uniqid(),
    ], $paymentOverrides));

    return [$order, $payment];
}

function refundFor(Payment $payment, array $overrides = []): PaymentRefund
{
    return PaymentRefund::factory()->create(array_merge([
        PaymentRefundSchema::PAYMENT_ID => $payment->{PaymentSchema::ID},
        PaymentRefundSchema::ORDER_ID => $payment->{PaymentSchema::ORDER_ID},
        PaymentRefundSchema::AMOUNT => 40.0,
        PaymentRefundSchema::REASON => 'customer return',
        PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::PROCESSING,
        PaymentRefundSchema::TRANSACTION_ID => 'rf-'.uniqid(),
    ], $overrides));
}

function refundWebhook(array $outcome): array
{
    return [
        ['refund' => array_merge(['status' => 'success'], $outcome)],
        ['X-Test-Signature' => 'secret'],
    ];
}

function assertRefundedAmountMatchesLedger(Order $order): void
{
    // The accounting invariant (gap-B4 plan §7): the order's refunded
    // amount always equals the sum of its successful, non-reversed
    // ledger entries.
    $ledgerMinor = (int) round(((float) PaymentRefund::query()
        ->where(PaymentRefundSchema::ORDER_ID, $order->{OrderSchema::ID})
        ->where(PaymentRefundSchema::STATUS, PaymentRefundStatusEnum::SUCCESS)
        ->sum(PaymentRefundSchema::AMOUNT)) * 100);

    expect((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe($ledgerMinor / 100.0);
}

it('confirms a processing refund through a success webhook and applies the order accounting', function () {
    [$order, $payment] = paidGatewayOrder();
    $refund = refundFor($payment);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'gateway_refund_id' => 'rf-gateway-1',
        'gateway_payment_id' => $payment->{PaymentSchema::GATEWAY_TRANSACTION_ID},
        'amount_minor' => 4000,
    ]))->assertOk()->assertJsonPath('data.received', true);

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::SUCCESS)
        ->and($refund->refresh()->{PaymentRefundSchema::TRANSACTION_ID})->toBe('rf-gateway-1')
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(40.0)
        ->and($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PARTIALLY_REFUNDED);

    assertRefundedAmountMatchesLedger($order);
});

it('confirms a pending refund whose webhook outran the accept response', function () {
    [$order, $payment] = paidGatewayOrder();
    $refund = refundFor($payment, [PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::PENDING]);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'success',
    ]))->assertOk();

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::SUCCESS)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(40.0);
});

it('fails a processing refund and frees its reservation', function () {
    [, $payment] = paidGatewayOrder();
    $refund = refundFor($payment, [PaymentRefundSchema::AMOUNT => 100.0]);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'failed',
    ]))->assertOk();

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::FAILED);

    // The reservation is gone: a fresh refund of the full amount is accepted.
    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), [
            PaymentRefundSchema::AMOUNT => '100.00',
            PaymentRefundSchema::REASON => 'retry after gateway failure',
        ])->assertCreated();
});

it('reverses a finalized refund and restores the order accounting', function () {
    [, $payment] = paidGatewayOrder();
    $refund = refundFor($payment, [
        PaymentRefundSchema::AMOUNT => 100.0,
        PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::SUCCESS,
        PaymentRefundSchema::TRANSACTION_ID => 'rf-finalized',
    ]);
    $order = Order::query()->find($payment->{PaymentSchema::ORDER_ID});
    $order->update([
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::REFUNDED,
        OrderSchema::REFUNDED_AMOUNT => 100.0,
    ]);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'reversed',
        'gateway_refund_id' => 'rf-finalized',
    ]))->assertOk();

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::REVERSED)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0)
        ->and($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID);

    assertRefundedAmountMatchesLedger($order);
});

it('reverses a refund the success webhook never reached, from every pre-terminal state', function (PaymentRefundStatusEnum $state) {
    [, $payment] = paidGatewayOrder();
    $refund = refundFor($payment, [PaymentRefundSchema::STATUS => $state]);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'reversed',
    ]))->assertOk();

    // No accounting was ever applied for this refund — the reversal only
    // finalizes the row and releases the reservation.
    $order = Order::query()->find($payment->{PaymentSchema::ORDER_ID});
    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::REVERSED)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0)
        ->and($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID);
})->with([
    'pending (webhook beat the accept response home)' => PaymentRefundStatusEnum::PENDING,
    'processing' => PaymentRefundStatusEnum::PROCESSING,
    'reconciliation_required (accept timed out, reversal landed first)' => PaymentRefundStatusEnum::RECONCILIATION_REQUIRED,
]);

it('releases the reservation when a pre-terminal refund is reversed', function () {
    [, $payment] = paidGatewayOrder();
    $refund = refundFor($payment, [PaymentRefundSchema::AMOUNT => 100.0]);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'reversed',
    ]))->assertOk();

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), [
            PaymentRefundSchema::AMOUNT => '100.00',
            PaymentRefundSchema::REASON => 'refund re-issued after reversal',
        ])->assertCreated();
});

it('acks a duplicate success webhook without applying accounting twice', function () {
    [$order, $payment] = paidGatewayOrder();
    $refund = refundFor($payment);
    $delivery = refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'success',
    ]);

    $this->postJson($this->baseUrl('/webhook/test'), ...$delivery)->assertOk();
    $this->postJson($this->baseUrl('/webhook/test'), ...$delivery)->assertOk();

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::SUCCESS)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(40.0)
        ->and($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PARTIALLY_REFUNDED)
        ->and(PaymentRefund::query()->count())->toBe(1);
});

it('acks a late success after reversal without regressing the ledger', function () {
    [$order, $payment] = paidGatewayOrder();
    $refund = refundFor($payment);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'reversed',
    ]))->assertOk();

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'success',
    ]))->assertOk();

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::REVERSED)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0)
        ->and($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID);
});

it('acks a webhook for a refund Baxela never issued', function () {
    paidGatewayOrder();

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => (string) Str::uuid(),
        'status' => 'success',
    ]))->assertOk()->assertJsonPath('data.received', true);

    expect(PaymentRefund::query()->count())->toBe(0);
});

it('rejects a refund webhook that fails signature verification before correlating anything', function () {
    [, $payment] = paidGatewayOrder();
    $refund = refundFor($payment);

    [$payload] = refundWebhook(['reference' => $refund->{PaymentRefundSchema::UUID}, 'status' => 'success']);

    $this->postJson($this->baseUrl('/webhook/test'), $payload, ['X-Test-Signature' => 'forged'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::PROCESSING);
});

it('never finalizes on an amount mismatch', function () {
    [$order, $payment] = paidGatewayOrder();
    $refund = refundFor($payment);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'success',
        'amount_minor' => 999999,
    ]))->assertOk();

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::PROCESSING)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0);
});

it('never finalizes on a payment association mismatch', function () {
    [$order, $payment] = paidGatewayOrder();
    $refund = refundFor($payment);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'success',
        'gateway_payment_id' => 'psp-belongs-to-someone-else',
    ]))->assertOk();

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::PROCESSING)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0);
});

it('never finalizes on a currency mismatch', function () {
    $currency = CurrencyFactory::new()->create([CurrencySchema::CODE => 'EUR']);
    [$order, $payment] = paidGatewayOrder(paymentOverrides: [
        PaymentSchema::CURRENCY_ID => $currency->id,
    ]);
    $refund = refundFor($payment);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'success',
        'currency' => 'USD',
    ]))->assertOk();

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::PROCESSING)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0);
});

it('correlates through the gateway refund id when the uuid is not echoed', function () {
    [$order, $payment] = paidGatewayOrder();
    $refund = refundFor($payment, [PaymentRefundSchema::TRANSACTION_ID => 'rf-known']);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        // Some lifecycle events may not echo the merchant reference —
        // the event's own refund id is the documented fallback key.
        'reference' => 'not-our-merchant-reference',
        'gateway_refund_id' => 'rf-known',
        'status' => 'success',
    ]))->assertOk();

    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::SUCCESS)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(40.0);
});

it('routes a definitive failure on a finalized refund to the reversal path', function () {
    [, $payment] = paidGatewayOrder();
    $refund = refundFor($payment, [
        PaymentRefundSchema::AMOUNT => 100.0,
        PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::SUCCESS,
    ]);
    $order = Order::query()->find($payment->{PaymentSchema::ORDER_ID});
    $order->update([
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::REFUNDED,
        OrderSchema::REFUNDED_AMOUNT => 100.0,
    ]);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'failed',
    ]))->assertOk();

    // The accounting had been applied and the gateway walked the refund
    // back — the ledger reverses instead of silently keeping it.
    expect($refund->refresh()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::REVERSED)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0)
        ->and($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID);
});

it('holds the reservation of a stale processing row while no outcome arrives', function () {
    [, $payment] = paidGatewayOrder();
    refundFor($payment, [PaymentRefundSchema::AMOUNT => 60.0]);

    // No webhook, no age-based transition: the processing amount stays
    // counted against the refundable balance.
    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), [
            PaymentRefundSchema::AMOUNT => '60.00',
            PaymentRefundSchema::REASON => 'would exceed the reserved balance',
        ])->assertStatus(400)
        ->assertJsonPath('code', 'payment.refund.exceeds_refundable');
});

it('fires the refunded event with uncollided identifiers', function () {
    Event::fake();

    [, $payment] = paidGatewayOrder();
    $refund = refundFor($payment);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'success',
    ]))->assertOk();

    Event::assertDispatched(function (
        PaymentRefundedEvent $event,
    ) use ($refund, $payment): bool {
        // PaymentRefundSchema::ID and PaymentSchema::ID are both 'id' —
        // the fill map must not collapse them.
        return $event->id === (int) $refund->{PaymentRefundSchema::ID}
            && $event->payment_id === (int) $payment->{PaymentSchema::ID}
            && $event->order_id === (int) $payment->{PaymentSchema::ORDER_ID};
    });
});

it('fires the reversal event with the accounting flag set', function () {
    Event::fake();

    [, $payment] = paidGatewayOrder();
    $refund = refundFor($payment, [
        PaymentRefundSchema::AMOUNT => 100.0,
        PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::SUCCESS,
    ]);
    $order = Order::query()->find($payment->{PaymentSchema::ORDER_ID});
    $order->update([
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::REFUNDED,
        OrderSchema::REFUNDED_AMOUNT => 100.0,
    ]);

    $this->postJson($this->baseUrl('/webhook/test'), ...refundWebhook([
        'reference' => $refund->{PaymentRefundSchema::UUID},
        'status' => 'reversed',
    ]))->assertOk();

    Event::assertDispatched(function (
        PaymentRefundReversedEvent $event,
    ) use ($refund): bool {
        return $event->id === (int) $refund->{PaymentRefundSchema::ID}
            && $event->restored_accounting === true;
    });
});
