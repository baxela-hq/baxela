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

function nowpaymentsUnpaidOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
}

function nowpaymentsPendingPayment(Order $order): Payment
{
    $payment = Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => 'nowpayments',
        PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
    ]);

    // The order reference chosen at initiate — the payment's own id — is
    // what the IPN matches on.
    $payment->{PaymentSchema::TRANSACTION_ID} = (string) $payment->id;
    $payment->save();

    return $payment;
}

function nowpaymentsIpnBody(string $reference, array $overrides = []): array
{
    return array_merge([
        'payment_id' => 4607606000,
        'invoice_id' => 4607606111,
        'order_id' => $reference,
        'order_description' => 'Baxela ABCD2345',
        'payment_status' => 'finished',
        'price_amount' => 305,
        'price_currency' => 'usd',
        'network' => 'mainnet',
        'pay_currency' => 'btc',
        'pay_amount' => 0.0035,
        'actually_paid' => 0.0035,
        'outcomeurl' => 'https://nowpayments.io/payment/4607606000',
    ], $overrides);
}

/**
 * Signed delivery headers — the real HMAC-SHA512 path runs inside the
 * driver, so no client stubbing is needed (unlike PayPal's certificate
 * check). Signing a different body than the one delivered simulates
 * tampering.
 */
function nowpaymentsSigHeader(array $ipn, string $secret = 'np-ipn-test'): array
{
    return ['x-nowpayments-sig' => nowpaymentsIpnSignature($ipn, $secret)];
}

function nowpaymentsIpnSignature(array $ipn, string $secret): string
{
    return hash_hmac(
        'sha512',
        json_encode(nowpaymentsSortedKeys($ipn), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $secret,
    );
}

/**
 * @param  array<string, mixed>  $value
 * @return array<string, mixed>
 */
function nowpaymentsSortedKeys(array $value): array
{
    ksort($value);

    foreach ($value as &$item) {
        if (is_array($item)) {
            $item = nowpaymentsSortedKeys($item);
        }
    }

    return $value;
}

beforeEach(function () {
    config(['payment.nowpayments.ipn_secret' => 'np-ipn-test']);
});

it('settles an order through a finished ipn', function () {
    $order = nowpaymentsUnpaidOrder();
    $payment = nowpaymentsPendingPayment($order);
    $body = nowpaymentsIpnBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/nowpayments'), $body, nowpaymentsSigHeader($body))
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();
});

it('acks a replayed ipn as a no-op', function () {
    $order = nowpaymentsUnpaidOrder();
    $payment = nowpaymentsPendingPayment($order);
    $body = nowpaymentsIpnBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/nowpayments'), $body, nowpaymentsSigHeader($body))->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};
    $this->postJson($this->baseUrl('/webhook/nowpayments'), $body, nowpaymentsSigHeader($body))->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('acks a late expiry after settlement as a no-op', function () {
    $order = nowpaymentsUnpaidOrder();
    $payment = nowpaymentsPendingPayment($order);
    $body = nowpaymentsIpnBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/nowpayments'), $body, nowpaymentsSigHeader($body))->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};

    // Out-of-order delivery: an expiry arriving after the settle must not
    // overwrite the success — HandleWebhookAction only mutates PENDING.
    $expired = nowpaymentsIpnBody((string) $payment->id, [
        'payment_id' => 4607606999,
        'payment_status' => 'expired',
    ]);
    $this->postJson($this->baseUrl('/webhook/nowpayments'), $expired, nowpaymentsSigHeader($expired))->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('marks the payment failed on a failed ipn without touching the order', function () {
    $order = nowpaymentsUnpaidOrder();
    $payment = nowpaymentsPendingPayment($order);
    $body = nowpaymentsIpnBody((string) $payment->id, ['payment_status' => 'failed']);

    $this->postJson($this->baseUrl('/webhook/nowpayments'), $body, nowpaymentsSigHeader($body))
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::FAILED)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('keeps the payment pending on non-terminal ipns', function () {
    $order = nowpaymentsUnpaidOrder();
    $payment = nowpaymentsPendingPayment($order);
    $body = nowpaymentsIpnBody((string) $payment->id, ['payment_status' => 'confirming']);

    // On-chain states crawl along for minutes to hours; the terminal
    // finished ipn settles, intermediate ones must not touch the payment.
    $this->postJson($this->baseUrl('/webhook/nowpayments'), $body, nowpaymentsSigHeader($body))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a well-formed ipn whose reference matches no payment', function () {
    $order = nowpaymentsUnpaidOrder();
    $payment = nowpaymentsPendingPayment($order);
    $body = nowpaymentsIpnBody('999');

    $this->postJson($this->baseUrl('/webhook/nowpayments'), $body, nowpaymentsSigHeader($body))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects an ipn with a bad signature', function () {
    $order = nowpaymentsUnpaidOrder();
    $payment = nowpaymentsPendingPayment($order);
    $body = nowpaymentsIpnBody((string) $payment->id);

    // Signed for a different order: any tampered field produces the same
    // invalid outcome.
    $this->postJson($this->baseUrl('/webhook/nowpayments'), $body, nowpaymentsSigHeader(nowpaymentsIpnBody('999')))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects ipns when the ipn secret is not configured', function () {
    config(['payment.nowpayments.ipn_secret' => null]);
    $order = nowpaymentsUnpaidOrder();
    $payment = nowpaymentsPendingPayment($order);
    $body = nowpaymentsIpnBody((string) $payment->id);

    $this->postJson($this->baseUrl('/webhook/nowpayments'), $body, nowpaymentsSigHeader($body))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});
