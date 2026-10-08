<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Payment\Gateways\MercadopagoClient;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function mercadopagoUnpaidOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
}

function mercadopagoPendingPayment(Order $order): Payment
{
    $payment = Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => 'mercadopago',
        PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
    ]);

    // The external reference chosen at initiate — the payment's own id —
    // is what the fetched payment resource matches on.
    $payment->{PaymentSchema::TRANSACTION_ID} = (string) $payment->id;
    $payment->save();

    return $payment;
}

/**
 * Payment resource as GET /v1/payments/{id} returns it.
 */
function mercadopagoFetchedPayment(string $status, string $reference): object
{
    return (object) [
        'id' => 123456789,
        'status' => $status,
        'external_reference' => $reference,
        'transaction_amount' => 305,
        'currency_id' => 'BRL',
    ];
}

/**
 * Signed shadow-notification headers — the real x-signature verification
 * runs inside the driver. Signing for a different payment id simulates
 * tampering.
 */
function mercadopagoSignedHeaders(string $dataId = '123456789', string $secret = 'mp-webhook-test'): array
{
    $manifest = "id:{$dataId};request-id:req_1;ts:1762300000;";

    return [
        'x-request-id' => 'req_1',
        'x-signature' => 'ts=1762300000,v1='.hash_hmac('sha256', $manifest, $secret),
    ];
}

/**
 * Partial mock keeps the real signature verification (pure computation)
 * and stubs only the payment fetch.
 */
function bindMercadopagoClient(object $payment, int $times = 1): void
{
    $client = Mockery::mock(MercadopagoClient::class)->makePartial();
    $client->shouldReceive('getPayment')->times($times)->andReturn($payment);
    app()->instance(MercadopagoClient::class, $client);
}

beforeEach(function () {
    config(['payment.mercadopago.webhook_secret' => 'mp-webhook-test']);
});

it('settles an order through an approved payment notification', function () {
    $order = mercadopagoUnpaidOrder();
    $payment = mercadopagoPendingPayment($order);
    bindMercadopagoClient(mercadopagoFetchedPayment('approved', (string) $payment->id));

    $this->postJson($this->baseUrl('/webhook/mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ], mercadopagoSignedHeaders())
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();
});

it('acks a replayed notification as a no-op', function () {
    $order = mercadopagoUnpaidOrder();
    $payment = mercadopagoPendingPayment($order);
    bindMercadopagoClient(mercadopagoFetchedPayment('approved', (string) $payment->id), 2);

    $this->postJson($this->baseUrl('/webhook/mercadopago'), ['type' => 'payment', 'data' => ['id' => '123456789']], mercadopagoSignedHeaders())->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};
    $this->postJson($this->baseUrl('/webhook/mercadopago'), ['type' => 'payment', 'data' => ['id' => '123456789']], mercadopagoSignedHeaders())->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('acks a late rejection after settlement as a no-op', function () {
    $order = mercadopagoUnpaidOrder();
    $payment = mercadopagoPendingPayment($order);
    $client = Mockery::mock(MercadopagoClient::class)->makePartial();
    // First delivery fetches an approved payment; the out-of-order replay
    // fetches a rejected one — the settled success must not be overwritten
    // because HandleWebhookAction only mutates PENDING.
    $client->shouldReceive('getPayment')->twice()->andReturnUsing(
        fn (): object => mercadopagoFetchedPayment('approved', (string) $payment->id),
        fn (): object => mercadopagoFetchedPayment('rejected', (string) $payment->id),
    );
    $this->app->instance(MercadopagoClient::class, $client);

    $this->postJson($this->baseUrl('/webhook/mercadopago'), ['type' => 'payment', 'data' => ['id' => '123456789']], mercadopagoSignedHeaders())->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};
    $this->postJson($this->baseUrl('/webhook/mercadopago'), ['type' => 'payment', 'data' => ['id' => '123456789']], mercadopagoSignedHeaders())->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('marks the payment failed on a rejected payment without touching the order', function () {
    $order = mercadopagoUnpaidOrder();
    $payment = mercadopagoPendingPayment($order);
    bindMercadopagoClient(mercadopagoFetchedPayment('rejected', (string) $payment->id));

    $this->postJson($this->baseUrl('/webhook/mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ], mercadopagoSignedHeaders())
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::FAILED)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('keeps the payment pending on non-terminal payment statuses', function (string $status) {
    $order = mercadopagoUnpaidOrder();
    $payment = mercadopagoPendingPayment($order);
    bindMercadopagoClient(mercadopagoFetchedPayment($status, (string) $payment->id));

    // Pix vouchers and Boleto slips sit here for hours; the terminal
    // approved notification settles, intermediate ones must not.
    $this->postJson($this->baseUrl('/webhook/mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ], mercadopagoSignedHeaders())
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
})->with([
    'pending' => ['pending'],
    'in process' => ['in_process'],
]);

it('rejects a well-formed notification whose reference matches no payment', function () {
    $order = mercadopagoUnpaidOrder();
    $payment = mercadopagoPendingPayment($order);
    bindMercadopagoClient(mercadopagoFetchedPayment('approved', '999'));

    $this->postJson($this->baseUrl('/webhook/mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ], mercadopagoSignedHeaders())
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a notification with a bad signature', function () {
    $order = mercadopagoUnpaidOrder();
    $payment = mercadopagoPendingPayment($order);

    // The signature is checked before anything is fetched.
    $client = Mockery::mock(MercadopagoClient::class)->makePartial();
    $client->shouldReceive('getPayment')->never();
    $this->app->instance(MercadopagoClient::class, $client);

    $this->postJson($this->baseUrl('/webhook/mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ], mercadopagoSignedHeaders('999'))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects notifications when the webhook secret is not configured', function () {
    config(['payment.mercadopago.webhook_secret' => null]);
    $order = mercadopagoUnpaidOrder();
    $payment = mercadopagoPendingPayment($order);

    $this->postJson($this->baseUrl('/webhook/mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ], mercadopagoSignedHeaders())
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});
