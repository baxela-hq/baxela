<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Payment\Actions\Admin\PaymentRefund\ReverseRefundAction;
use Modules\Payment\Gateways\Drivers\PaypalPaymentDriver;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function paidOrderWithManualPayment(float $amount = 100.0, array $orderOverrides = []): array
{
    $order = Order::query()->create(array_merge([
        OrderSchema::USER_ID => 1,
        OrderSchema::ORDER_CODE => 'RC'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::PAID_AT => now(),
        OrderSchema::TOTAL_AMOUNT => $amount,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ], $orderOverrides));

    $payment = Payment::query()->create([
        PaymentSchema::ORDER_ID => $order->{OrderSchema::ID},
        PaymentSchema::METHOD => PaymentMethodEnum::MANUAL,
        PaymentSchema::STATUS => PaymentStatusEnum::SUCCESS,
        PaymentSchema::AMOUNT => $amount,
        PaymentSchema::TRANSACTION_ID => 'offline-'.uniqid(),
    ]);

    return [$order, $payment];
}

/**
 * Same as above but for a gateway method, carrying the gateway-side
 * payment id the refund APIs key on.
 */
function paidOrderWithGatewayPayment(float $amount = 100.0, PaymentMethodEnum $method = PaymentMethodEnum::MANUAL): array
{
    [$order, $payment] = paidOrderWithManualPayment($amount);

    $payment->update([
        PaymentSchema::METHOD => $method,
        PaymentSchema::GATEWAY_TRANSACTION_ID => 'psp-'.uniqid(),
    ]);

    return [$order->refresh(), $payment->refresh()];
}

function refundPayload(float $amount, string $reason = 'customer return'): array
{
    return [
        PaymentRefundSchema::AMOUNT => number_format($amount, 2, '.', ''),
        PaymentRefundSchema::REASON => $reason,
    ];
}

it('executes a full refund through the ledger and marks the order refunded', function () {
    [$order, $payment] = paidOrderWithManualPayment(100);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(100))
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentRefundStatusEnum::SUCCESS->value);

    expect($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::REFUNDED)
        ->and((float) $order->{OrderSchema::REFUNDED_AMOUNT})->toBe(100.0)
        ->and(PaymentRefund::query()->count())->toBe(1);
});

it('rejects a refund on a gateway without refund support and records the failed attempt', function () {
    $order = Order::query()->create([
        OrderSchema::USER_ID => 1,
        OrderSchema::ORDER_CODE => 'RC'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::PAID_AT => now(),
        OrderSchema::TOTAL_AMOUNT => 100.0,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);

    $payment = Payment::query()->create([
        PaymentSchema::ORDER_ID => $order->{OrderSchema::ID},
        // NowPayments is the remaining non-refundable driver: crypto
        // payments cannot be reversed provider-side. (Adyen grew a refund
        // driver in gap-B4.)
        PaymentSchema::METHOD => PaymentMethodEnum::NOWPAYMENTS,
        PaymentSchema::STATUS => PaymentStatusEnum::SUCCESS,
        PaymentSchema::AMOUNT => 100.0,
        PaymentSchema::TRANSACTION_ID => 'nowpayments-'.uniqid(),
    ]);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(100))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.refund.not_supported');

    // No money moved: the ledger keeps the failed attempt, the order none the richer.
    expect(PaymentRefund::query()->count())->toBe(1)
        ->and(PaymentRefund::query()->first()->{PaymentRefundSchema::STATUS})->toBe(PaymentRefundStatusEnum::FAILED)
        ->and((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0);
});

it('holds an async-accepted refund in processing without firing the refunded event', function () {
    Event::fake();

    [$order, $payment] = paidOrderWithGatewayPayment(100, PaymentMethodEnum::PAYPAL);

    $this->mock(PaypalPaymentDriver::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refund')->once()->andReturn(new PaymentRefundResult(
            transaction_id: 'rf-async-1',
            accepted_pending: true,
        ));
    });

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(100))
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentRefundStatusEnum::PROCESSING->value);

    // Accepted is not completed: no refunded event, no order accounting,
    // and the reserved amount still fences the refundable balance.
    Event::assertNotDispatched(PaymentRefundedEvent::class);

    expect((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0)
        ->and($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(1))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.refund.exceeds_refundable');
});

it('keeps the webhook outcome when it beats the accept response home', function () {
    [$order, $payment] = paidOrderWithGatewayPayment(100, PaymentMethodEnum::PAYPAL);

    $this->mock(PaypalPaymentDriver::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refund')->once()->andReturnUsing(
            function (PaymentRefundInput $input): PaymentRefundResult {
                // Simulate the gateway webhook arriving before the accept
                // HTTP response: the row is reversed out from under the
                // still-running accept path.
                $refund = PaymentRefund::query()
                    ->where(PaymentRefundSchema::UUID, $input->idempotency_key)
                    ->first();

                app(ReverseRefundAction::class)->handle($refund);

                return new PaymentRefundResult(transaction_id: 'rf-raced', accepted_pending: true);
            },
        );
    });

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(100))
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentRefundStatusEnum::REVERSED->value);

    // The conditional accept-path update must not overwrite the reversal:
    // no accounting was ever applied and the reservation is released.
    expect((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(0.0)
        ->and($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and(PaymentRefund::query()->first()->{PaymentRefundSchema::TRANSACTION_ID})->toBeNull();
});

it('derives partially_refunded from order-level totals while remainder stays', function () {
    [$order, $payment] = paidOrderWithManualPayment(100);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(40))
        ->assertCreated();

    expect($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PARTIALLY_REFUNDED)
        ->and((float) $order->{OrderSchema::REFUNDED_AMOUNT})->toBe(40.0);

    // the remainder is still refundable
    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(60))
        ->assertCreated();

    expect($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::REFUNDED)
        ->and((float) $order->{OrderSchema::REFUNDED_AMOUNT})->toBe(100.0);
});

it('rejects refunds beyond the remaining refundable balance', function () {
    [$order, $payment] = paidOrderWithManualPayment(100);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(60))
        ->assertCreated();

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(50))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.refund.exceeds_refundable');

    // only the first refund settled on the order
    expect((float) $order->refresh()->{OrderSchema::REFUNDED_AMOUNT})->toBe(60.0);
});

it('rejects refunds against payments that are not successful', function () {
    [$order, $payment] = paidOrderWithManualPayment(100);
    $payment->update([PaymentSchema::STATUS => PaymentStatusEnum::PENDING]);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(10))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.refund.invalid_status');
});

it('derives order refund status from aggregate totals across payments', function () {
    [$order, $first] = paidOrderWithManualPayment(60);
    // a second successful payment on the same order (retry scenario)
    $second = Payment::query()->create([
        PaymentSchema::ORDER_ID => $order->{OrderSchema::ID},
        PaymentSchema::METHOD => PaymentMethodEnum::MANUAL,
        PaymentSchema::STATUS => PaymentStatusEnum::SUCCESS,
        PaymentSchema::AMOUNT => 40,
        PaymentSchema::TRANSACTION_ID => 'offline-2',
    ]);

    // refunding the first payment fully must NOT mark the order refunded
    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$first->id}/refunds"), refundPayload(60))
        ->assertCreated();

    expect($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PARTIALLY_REFUNDED)
        ->and((float) $order->{OrderSchema::REFUNDED_AMOUNT})->toBe(60.0);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$second->id}/refunds"), refundPayload(40))
        ->assertCreated();

    expect($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::REFUNDED);
});

it('keeps reconciliation-required amounts reserved until resolved', function () {
    [$order, $payment] = paidOrderWithManualPayment(100);

    // simulate an ambiguous gateway outcome directly (timeout path)
    $held = PaymentRefund::query()->create([
        PaymentRefundSchema::UUID => (string) Str::uuid(),
        PaymentRefundSchema::PAYMENT_ID => $payment->id,
        PaymentRefundSchema::ORDER_ID => $order->{OrderSchema::ID},
        PaymentRefundSchema::AMOUNT => 60,
        PaymentRefundSchema::REASON => 'outcome unknown',
        PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::RECONCILIATION_REQUIRED,
        PaymentRefundSchema::REFUNDED_BY => 1,
    ]);

    // the reserved 60 still counts: only 40 remains refundable
    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(50))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.refund.exceeds_refundable');

    // resolving as failed frees the amount
    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl("/admin/refunds/{$held->id}/resolve"), [
            PaymentRefundSchema::STATUS => 'failed',
        ])->assertOk();

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(100))
        ->assertCreated();

    expect($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::REFUNDED);
});

it('resolves an ambiguous refund as success with the accounting applied', function () {
    [$order, $payment] = paidOrderWithManualPayment(100);

    $held = PaymentRefund::query()->create([
        PaymentRefundSchema::UUID => (string) Str::uuid(),
        PaymentRefundSchema::PAYMENT_ID => $payment->id,
        PaymentRefundSchema::ORDER_ID => $order->{OrderSchema::ID},
        PaymentRefundSchema::AMOUNT => 100,
        PaymentRefundSchema::REASON => 'outcome unknown',
        PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::RECONCILIATION_REQUIRED,
        PaymentRefundSchema::REFUNDED_BY => 1,
    ]);

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl("/admin/refunds/{$held->id}/resolve"), [
            PaymentRefundSchema::STATUS => 'success',
            PaymentRefundSchema::TRANSACTION_ID => 're_late_1',
        ])->assertOk()
        ->assertJsonPath('data.status', PaymentRefundStatusEnum::SUCCESS->value)
        ->assertJsonPath('data.transaction_id', 're_late_1');

    expect($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::REFUNDED)
        ->and((float) $order->{OrderSchema::REFUNDED_AMOUNT})->toBe(100.0);
});

it('lists the refund history for a payment', function () {
    [$order, $payment] = paidOrderWithManualPayment(100);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(30))
        ->assertCreated();

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', '30.00');
});

it('denies the refund endpoints without permission', function () {
    [, $payment] = paidOrderWithManualPayment(100);

    $this->actingAs(User::factory()->create())
        ->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), refundPayload(10))
        ->assertStatus(403);
});
