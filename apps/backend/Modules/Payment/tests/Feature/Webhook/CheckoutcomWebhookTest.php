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

function checkoutcomUnpaidOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
}

function checkoutcomPendingPayment(Order $order): Payment
{
    $payment = Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => 'checkoutcom',
        PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
    ]);

    // The reference chosen at initiate — the payment's own id — is what
    // the webhook's data.reference matches on.
    $payment->{PaymentSchema::TRANSACTION_ID} = (string) $payment->id;
    $payment->save();

    return $payment;
}

function checkoutcomEventBody(string $reference, string $type = 'payment_captured'): array
{
    return [
        'id' => 'evt_jclzjykecuuu7mmesbk63alzoa',
        'type' => $type,
        'data' => [
            'id' => 'pay_mbabxz242c5syclbu3swqk43ea',
            'reference' => $reference,
            'status' => 'Captured',
            'amount' => 30500,
            'currency' => 'USD',
        ],
    ];
}

/**
 * Signed delivery headers — the real raw-body HMAC path runs inside the
 * driver, so no client stubbing is needed at all. Signing different bytes
 * than the ones delivered simulates tampering.
 */
function checkoutcomSignedHeaders(array $body, ?array $signBody = null): array
{
    $raw = json_encode($signBody ?? $body, JSON_THROW_ON_ERROR);

    return ['cko-signature' => hash_hmac('sha256', $raw, 'cko-webhook-test')];
}

beforeEach(function () {
    config(['payment.checkoutcom.webhook_secret' => 'cko-webhook-test']);
});

it('settles an order through a captured checkoutcom webhook', function () {
    $order = checkoutcomUnpaidOrder();
    $payment = checkoutcomPendingPayment($order);
    $body = checkoutcomEventBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $body, checkoutcomSignedHeaders($body))
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();
});

it('acks a replayed webhook as a no-op', function () {
    $order = checkoutcomUnpaidOrder();
    $payment = checkoutcomPendingPayment($order);
    $body = checkoutcomEventBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $body, checkoutcomSignedHeaders($body))->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};
    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $body, checkoutcomSignedHeaders($body))->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('acks a late decline after settlement as a no-op', function () {
    $order = checkoutcomUnpaidOrder();
    $payment = checkoutcomPendingPayment($order);
    $body = checkoutcomEventBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $body, checkoutcomSignedHeaders($body))->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};

    // Out-of-order delivery: a decline arriving after the settle must not
    // overwrite the success — HandleWebhookAction only mutates PENDING.
    $declined = checkoutcomEventBody((string) $payment->id, 'payment_declined');
    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $declined, checkoutcomSignedHeaders($declined))->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('marks the payment failed on a declined event without touching the order', function () {
    $order = checkoutcomUnpaidOrder();
    $payment = checkoutcomPendingPayment($order);
    $body = checkoutcomEventBody((string) $payment->id, 'payment_declined');

    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $body, checkoutcomSignedHeaders($body))
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::FAILED)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('keeps the payment pending on approved-but-not-captured events', function () {
    $order = checkoutcomUnpaidOrder();
    $payment = checkoutcomPendingPayment($order);
    $body = checkoutcomEventBody((string) $payment->id, 'payment_approved');

    // On auto-capture accounts approval is immediately followed by
    // payment_captured; an approval alone must not settle the order.
    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $body, checkoutcomSignedHeaders($body))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a well-formed event whose reference matches no payment', function () {
    $order = checkoutcomUnpaidOrder();
    $payment = checkoutcomPendingPayment($order);
    $body = checkoutcomEventBody('999');

    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $body, checkoutcomSignedHeaders($body))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a checkoutcom webhook with a bad signature', function () {
    $order = checkoutcomUnpaidOrder();
    $payment = checkoutcomPendingPayment($order);
    $body = checkoutcomEventBody((string) $payment->id);

    // Signed for a different payload: any post-signing modification
    // produces the same invalid outcome.
    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $body, checkoutcomSignedHeaders($body, checkoutcomEventBody('999')))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects webhooks when the webhook secret is not configured', function () {
    config(['payment.checkoutcom.webhook_secret' => null]);
    $order = checkoutcomUnpaidOrder();
    $payment = checkoutcomPendingPayment($order);
    $body = checkoutcomEventBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/checkoutcom'), $body, checkoutcomSignedHeaders($body))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});
