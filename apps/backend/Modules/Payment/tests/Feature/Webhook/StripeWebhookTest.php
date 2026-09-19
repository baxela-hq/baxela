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

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    config(['payment.stripe.webhook_secret' => 'whsec_test']);
});

function stripeUnpaidOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
}

function stripePendingPayment(Order $order, string $sessionId): Payment
{
    return Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::TRANSACTION_ID => $sessionId,
        PaymentSchema::METHOD => 'stripe',
        PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
    ]);
}

it('settles an order through a signed stripe webhook', function () {
    $order = stripeUnpaidOrder();
    $payment = stripePendingPayment($order, 'cs_test_123');

    $body = json_encode([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_123', 'payment_status' => 'paid']],
    ]);
    $timestamp = time();
    $signature = sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_test'));

    $this->call('POST', $this->baseUrl('/webhook/stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk()->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();
});

it('rejects a stripe webhook signed with the wrong secret', function () {
    $order = stripeUnpaidOrder();
    $payment = stripePendingPayment($order, 'cs_test_123');

    $body = json_encode([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_123', 'payment_status' => 'paid']],
    ]);
    $timestamp = time();
    $signature = sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_wrong'));

    $this->call('POST', $this->baseUrl('/webhook/stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(400)->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});
