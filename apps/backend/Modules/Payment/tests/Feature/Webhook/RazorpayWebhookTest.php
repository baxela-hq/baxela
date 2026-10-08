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

function razorpayUnpaidOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
}

function razorpayPendingPayment(Order $order): Payment
{
    $payment = Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => 'razorpay',
        PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
    ]);

    // The reference chosen at initiate — the payment's own id — is what
    // the webhook's payment_link entity matches on.
    $payment->{PaymentSchema::TRANSACTION_ID} = (string) $payment->id;
    $payment->save();

    return $payment;
}

function razorpayEventBody(string $reference, string $event = 'payment_link.paid'): array
{
    return [
        'event' => $event,
        'payload' => [
            'payment_link' => [
                'entity' => [
                    'id' => 'plink_KfskYmqDPG9hXY',
                    'reference_id' => $reference,
                    'status' => 'paid',
                    'amount' => 30500,
                    'currency' => 'INR',
                    'short_url' => 'https://rzp.io/i/abc123',
                ],
            ],
        ],
    ];
}

/**
 * Signed delivery headers — the real raw-body HMAC path runs inside the
 * driver, so no client stubbing is needed at all. Signing different bytes
 * than the ones delivered simulates tampering.
 */
function razorpaySignedHeaders(array $body, ?array $signBody = null): array
{
    $raw = json_encode($signBody ?? $body, JSON_THROW_ON_ERROR);

    return ['x-razorpay-signature' => hash_hmac('sha256', $raw, 'rzp-webhook-test')];
}

beforeEach(function () {
    config(['payment.razorpay.webhook_secret' => 'rzp-webhook-test']);
});

it('settles an order through a paid razorpay webhook', function () {
    $order = razorpayUnpaidOrder();
    $payment = razorpayPendingPayment($order);
    $body = razorpayEventBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/razorpay'), $body, razorpaySignedHeaders($body))
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();
});

it('acks a replayed webhook as a no-op', function () {
    $order = razorpayUnpaidOrder();
    $payment = razorpayPendingPayment($order);
    $body = razorpayEventBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/razorpay'), $body, razorpaySignedHeaders($body))->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};
    $this->postJson($this->baseUrl('/webhook/razorpay'), $body, razorpaySignedHeaders($body))->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('acks a late cancellation after settlement as a no-op', function () {
    $order = razorpayUnpaidOrder();
    $payment = razorpayPendingPayment($order);
    $body = razorpayEventBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/razorpay'), $body, razorpaySignedHeaders($body))->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};

    // Out-of-order delivery: a cancellation arriving after the settle must
    // not overwrite the success — HandleWebhookAction only mutates PENDING.
    $cancelled = razorpayEventBody((string) $payment->id, 'payment_link.cancelled');
    $this->postJson($this->baseUrl('/webhook/razorpay'), $cancelled, razorpaySignedHeaders($cancelled))->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('marks the payment failed on a cancelled link without touching the order', function () {
    $order = razorpayUnpaidOrder();
    $payment = razorpayPendingPayment($order);
    $body = razorpayEventBody((string) $payment->id, 'payment_link.cancelled');

    $this->postJson($this->baseUrl('/webhook/razorpay'), $body, razorpaySignedHeaders($body))
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::FAILED)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('keeps the payment pending on failed payment attempts', function () {
    $order = razorpayUnpaidOrder();
    $payment = razorpayPendingPayment($order);
    $body = razorpayEventBody((string) $payment->id, 'payment.failed');

    // A failed attempt leaves the link payable — only payment_link.paid
    // or payment_link.cancelled are terminal.
    $this->postJson($this->baseUrl('/webhook/razorpay'), $body, razorpaySignedHeaders($body))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a well-formed event whose reference matches no payment', function () {
    $order = razorpayUnpaidOrder();
    $payment = razorpayPendingPayment($order);
    $body = razorpayEventBody('999');

    $this->postJson($this->baseUrl('/webhook/razorpay'), $body, razorpaySignedHeaders($body))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a razorpay webhook with a bad signature', function () {
    $order = razorpayUnpaidOrder();
    $payment = razorpayPendingPayment($order);
    $body = razorpayEventBody((string) $payment->id);

    // Signed for a different payload: any post-signing modification
    // produces the same invalid outcome.
    $this->postJson($this->baseUrl('/webhook/razorpay'), $body, razorpaySignedHeaders($body, razorpayEventBody('999')))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects webhooks when the webhook secret is not configured', function () {
    config(['payment.razorpay.webhook_secret' => null]);
    $order = razorpayUnpaidOrder();
    $payment = razorpayPendingPayment($order);
    $body = razorpayEventBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/razorpay'), $body, razorpaySignedHeaders($body))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});
