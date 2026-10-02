<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;
use Modules\Payment\Tests\Feature\Webhook\TestWebhookDriver;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function pendingPaymentFor(Order $order, int $transactionId): Payment
{
    return Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::TRANSACTION_ID => $transactionId,
        PaymentSchema::METHOD => 'manual',
        PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
    ]);
}

function unpaidOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
}

beforeEach(function () {
    config(['payment.drivers.test' => TestWebhookDriver::class]);
});

it('settles a payment webhook and drives its order to paid', function () {
    $order = unpaidOrder();
    $payment = pendingPaymentFor($order, 777);

    $this->postJson($this->baseUrl('/webhook/test'), [
        'transaction_id' => 777,
        'status' => 'success',
    ], ['X-Test-Signature' => 'secret'])
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->{OrderSchema::STATUS})->toBe(OrderStatusEnum::PROCESSING)
        ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();
});

it('acks a replayed webhook as a no-op without double transitions', function () {
    $order = unpaidOrder();
    $payment = pendingPaymentFor($order, 777);
    $payload = ['transaction_id' => 777, 'status' => 'success'];
    $headers = ['X-Test-Signature' => 'secret'];

    $this->postJson($this->baseUrl('/webhook/test'), $payload, $headers)->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};

    $this->postJson($this->baseUrl('/webhook/test'), $payload, $headers)->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('marks the payment failed without touching the order', function () {
    $order = unpaidOrder();
    $payment = pendingPaymentFor($order, 777);

    $this->postJson($this->baseUrl('/webhook/test'), [
        'transaction_id' => 777,
        'status' => 'failed',
    ], ['X-Test-Signature' => 'secret'])->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::FAILED)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a webhook whose transaction resolves to no payment', function () {
    unpaidOrder();

    $this->postJson($this->baseUrl('/webhook/test'), [
        'transaction_id' => 424242,
        'status' => 'success',
    ], ['X-Test-Signature' => 'secret'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');
});

it('rejects a webhook that fails signature verification', function () {
    $this->postJson($this->baseUrl('/webhook/test'), [
        'transaction_id' => 777,
        'status' => 'success',
    ], ['X-Test-Signature' => 'wrong'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');
});

it('rejects webhooks for the manual driver, which has none', function () {
    $this->postJson($this->baseUrl('/webhook/manual'), [
        'transaction_id' => 777,
        'status' => 'success',
    ])->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.not_supported');
});
