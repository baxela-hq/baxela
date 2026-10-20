<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Auth\Models\User;
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
