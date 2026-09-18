<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Core\Contracts\Events\Payment\PaymentFailedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentSucceededEvent;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function adminUnpaidOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
}

function adminPendingPayment(Order $order): Payment
{
    return Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => PaymentMethodEnum::MANUAL,
        PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
    ]);
}

it('denies a guest with 401', function () {
    $payment = adminPendingPayment(adminUnpaidOrder());

    $this->patchJson($this->baseUrl('/admin/payments/'.$payment->id), [
        'status' => 'success',
    ])->assertStatus(401);
});

it('confirms a pending payment and drives its order to paid', function () {
    Event::fake();
    $this->actingAs($this->superAdminUser());
    $order = adminUnpaidOrder();
    $payment = adminPendingPayment($order);

    $this->patchJson($this->baseUrl('/admin/payments/'.$payment->id), [
        'status' => 'success',
    ])->assertOk()->assertJsonPath('data.'.PaymentSchema::STATUS, PaymentStatusEnum::SUCCESS->value);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->{OrderSchema::STATUS})->toBe(OrderStatusEnum::PROCESSING)
        ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();

    Event::assertDispatched(PaymentSucceededEvent::class, 1);
});

it('fails a pending payment without touching the order', function () {
    Event::fake();
    $this->actingAs($this->superAdminUser());
    $order = adminUnpaidOrder();
    $payment = adminPendingPayment($order);

    $this->patchJson($this->baseUrl('/admin/payments/'.$payment->id), [
        'status' => 'failed',
    ])->assertOk()->assertJsonPath('data.'.PaymentSchema::STATUS, PaymentStatusEnum::FAILED->value);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::FAILED)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);

    Event::assertDispatched(PaymentFailedEvent::class, 1);
});

it('rejects settling an already settled payment', function () {
    $this->actingAs($this->superAdminUser());
    $order = adminUnpaidOrder();
    $payment = Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => PaymentMethodEnum::MANUAL,
        PaymentSchema::STATUS => PaymentStatusEnum::SUCCESS,
    ]);

    $this->patchJson($this->baseUrl('/admin/payments/'.$payment->id), [
        'status' => 'failed',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.update.invalid_status_transition');
});

it('rejects an invalid status', function () {
    $this->actingAs($this->superAdminUser());
    $payment = adminPendingPayment(adminUnpaidOrder());

    $this->patchJson($this->baseUrl('/admin/payments/'.$payment->id), [
        'status' => 'pending',
    ])->assertStatus(422);
});
