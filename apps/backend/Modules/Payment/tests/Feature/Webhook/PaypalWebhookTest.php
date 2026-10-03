<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Payment\Gateways\PaypalClient;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function paypalUnpaidOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
}

function paypalPendingPayment(Order $order, string $paypalOrderId): Payment
{
    return Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::TRANSACTION_ID => $paypalOrderId,
        PaymentSchema::METHOD => 'paypal',
        PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
    ]);
}

/**
 * Real PayPal deliveries sign the PAYPAL-* transmission headers; the
 * certificate check itself is the API call stubbed via PaypalClient.
 */
function paypalTransmission(): array
{
    return [
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
        'PAYPAL-CERT-URL' => 'https://api.paypal.com/cert',
        'PAYPAL-TRANSMISSION-ID' => 'tid_1',
        'PAYPAL-TRANSMISSION-SIG' => 'sig_1',
        'PAYPAL-TRANSMISSION-TIME' => '2026-10-03T00:00:00Z',
    ];
}

function paypalApprovedEvent(): array
{
    return [
        'id' => 'evt_1',
        'event_type' => 'CHECKOUT.ORDER.APPROVED',
        'resource' => ['id' => '5O190127TN3647153', 'status' => 'APPROVED'],
    ];
}

function paypalCaptureCompleted(): object
{
    return (object) [
        'id' => '5O190127TN3647153',
        'status' => 'COMPLETED',
        'purchase_units' => [
            (object) ['payments' => (object) ['captures' => [(object) ['id' => '41W53218KT3647153', 'status' => 'COMPLETED']]]],
        ],
    ];
}

beforeEach(function () {
    config(['payment.paypal.webhook_id' => 'wh_test']);

    $client = Mockery::mock(PaypalClient::class);
    $client->shouldReceive('verifyWebhookSignature')->andReturnTrue();
    $this->app->instance(PaypalClient::class, $client);
});

it('settles an order through an approved paypal webhook', function () {
    $order = paypalUnpaidOrder();
    $payment = paypalPendingPayment($order, '5O190127TN3647153');

    $client = Mockery::mock(PaypalClient::class);
    $client->shouldReceive('verifyWebhookSignature')->andReturnTrue();
    $client->shouldReceive('captureOrder')
        ->once()
        ->with('5O190127TN3647153')
        ->andReturn(paypalCaptureCompleted());
    $this->app->instance(PaypalClient::class, $client);

    $this->postJson($this->baseUrl('/webhook/paypal'), paypalApprovedEvent(), paypalTransmission())
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();
});

it('acks a replayed approval after settlement as a no-op', function () {
    $order = paypalUnpaidOrder();
    $payment = paypalPendingPayment($order, '5O190127TN3647153');

    $client = Mockery::mock(PaypalClient::class);
    $client->shouldReceive('verifyWebhookSignature')->andReturnTrue();
    // First delivery captures; PayPal 422s the replay with
    // ORDER_ALREADY_CAPTURED, which must still ack (idempotently).
    $client->shouldReceive('captureOrder')->twice()->andReturnUsing(
        fn (): object => paypalCaptureCompleted(),
        fn (): never => throw new RequestException(new Response(new Psr7Response(422, [], (string) json_encode([
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']],
        ])))),
    );
    $this->app->instance(PaypalClient::class, $client);

    $this->postJson($this->baseUrl('/webhook/paypal'), paypalApprovedEvent(), paypalTransmission())->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};
    $this->postJson($this->baseUrl('/webhook/paypal'), paypalApprovedEvent(), paypalTransmission())->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('marks the payment failed on a denied capture event without touching the order', function () {
    $order = paypalUnpaidOrder();
    $payment = paypalPendingPayment($order, '5O190127TN3647153');

    $this->postJson($this->baseUrl('/webhook/paypal'), [
        'id' => 'evt_1',
        'event_type' => 'PAYMENT.CAPTURE.DENIED',
        'resource' => [
            'id' => '41W53218KT3647153',
            'status' => 'DENIED',
            'supplementary_data' => ['related_ids' => ['order_id' => '5O190127TN3647153']],
        ],
    ], paypalTransmission())->assertOk()->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::FAILED)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a paypal webhook that fails signature verification', function () {
    $order = paypalUnpaidOrder();
    $payment = paypalPendingPayment($order, '5O190127TN3647153');

    $client = Mockery::mock(PaypalClient::class);
    $client->shouldReceive('verifyWebhookSignature')->andReturnFalse();
    $this->app->instance(PaypalClient::class, $client);

    $this->postJson($this->baseUrl('/webhook/paypal'), [
        'id' => 'evt_1',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => '41W53218KT3647153',
            'supplementary_data' => ['related_ids' => ['order_id' => '5O190127TN3647153']],
        ],
    ], paypalTransmission())
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a paypal webhook when the webhook id is not configured', function () {
    config(['payment.paypal.webhook_id' => null]);
    $order = paypalUnpaidOrder();
    paypalPendingPayment($order, '5O190127TN3647153');

    $this->postJson($this->baseUrl('/webhook/paypal'), [
        'id' => 'evt_1',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => '41W53218KT3647153',
            'supplementary_data' => ['related_ids' => ['order_id' => '5O190127TN3647153']],
        ],
    ], paypalTransmission())
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});
