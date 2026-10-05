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

function adyenUnpaidOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
}

function adyenPendingPayment(Order $order): Payment
{
    $payment = Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => 'adyen',
        PaymentSchema::STATUS => PaymentStatusEnum::PENDING,
    ]);

    // The merchant reference chosen at initiate — the payment's own id —
    // is what the webhook matches on.
    $payment->{PaymentSchema::TRANSACTION_ID} = (string) $payment->id;
    $payment->save();

    return $payment;
}

function adyenAuthItem(string $reference, array $overrides = []): array
{
    return array_merge([
        'pspReference' => '8515131751001000',
        'merchantAccountCode' => 'BaxelaECOM',
        'merchantReference' => $reference,
        'eventCode' => 'AUTHORISATION',
        'success' => true,
    ], $overrides);
}

/**
 * Signed delivery body — the real HMAC path runs inside the driver, so no
 * client stubbing is needed (unlike PayPal's certificate check). $signItem
 * (defaults to $item) lets tests tamper with the delivered item while
 * signing the original.
 */
function adyenDelivery(array $item, ?array $signItem = null): array
{
    $item['additionalData']['hmacSignature'] = adyenAuthSignature($signItem ?? $item);

    return ['live' => 'false', 'notificationItems' => [['NotificationRequestItem' => $item]]];
}

function adyenAuthSignature(array $item): string
{
    $success = ($item['success'] ?? false) === true || ($item['success'] ?? false) === 'true';

    $segments = [
        (string) ($item['pspReference'] ?? ''),
        (string) ($item['originalReference'] ?? ''),
        (string) ($item['merchantAccountCode'] ?? ''),
        (string) ($item['merchantReference'] ?? ''),
        $success ? 'true' : 'false',
    ];

    $canonical = implode(':', array_map(
        fn (string $segment): string => str_replace(['\\', ':'], ['\\\\', '\\:'], $segment),
        $segments,
    ));

    return base64_encode(hash_hmac('sha256', $canonical, 'adyen-hmac-test', true));
}

beforeEach(function () {
    config(['payment.adyen.hmac_key' => base64_encode('adyen-hmac-test')]);
});

it('settles an order through an authorised adyen webhook', function () {
    $order = adyenUnpaidOrder();
    $payment = adyenPendingPayment($order);

    $this->postJson($this->baseUrl('/webhook/adyen'), adyenDelivery(adyenAuthItem((string) $payment->id)))
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();
});

it('acks a replayed notification as a no-op', function () {
    $order = adyenUnpaidOrder();
    $payment = adyenPendingPayment($order);
    $body = adyenDelivery(adyenAuthItem((string) $payment->id));

    $this->postJson($this->baseUrl('/webhook/adyen'), $body)->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};
    $this->postJson($this->baseUrl('/webhook/adyen'), $body)->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('acks a late refusal after settlement as a no-op', function () {
    $order = adyenUnpaidOrder();
    $payment = adyenPendingPayment($order);

    $this->postJson($this->baseUrl('/webhook/adyen'), adyenDelivery(adyenAuthItem((string) $payment->id)))->assertOk();
    $paidAt = $order->refresh()->{OrderSchema::PAID_AT};

    // Out-of-order delivery: a refusal arriving after the settle must not
    // overwrite the success — HandleWebhookAction only mutates PENDING.
    $this->postJson($this->baseUrl('/webhook/adyen'), adyenDelivery(adyenAuthItem((string) $payment->id, [
        'pspReference' => '8836123456789012',
        'success' => false,
    ])))->assertOk();

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::SUCCESS)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
        ->and($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();
});

it('marks the payment failed on a refused notification without touching the order', function () {
    $order = adyenUnpaidOrder();
    $payment = adyenPendingPayment($order);

    $this->postJson($this->baseUrl('/webhook/adyen'), adyenDelivery(adyenAuthItem((string) $payment->id, ['success' => false])))
        ->assertOk()
        ->assertJsonPath('data.received', true);

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::FAILED)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects a well-formed notification whose reference matches no payment', function () {
    $order = adyenUnpaidOrder();
    $payment = adyenPendingPayment($order);

    $this->postJson($this->baseUrl('/webhook/adyen'), adyenDelivery(adyenAuthItem('999')))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects an adyen webhook with a bad signature', function () {
    $order = adyenUnpaidOrder();
    $payment = adyenPendingPayment($order);

    // Delivered for our reference but signed for another: any tampered
    // canonical field produces the same invalid outcome.
    $this->postJson($this->baseUrl('/webhook/adyen'), adyenDelivery(adyenAuthItem((string) $payment->id), adyenAuthItem('999')))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.webhook.invalid');

    expect($payment->refresh()->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});

it('rejects an adyen webhook when the hmac key is not configured', function () {
    config(['payment.adyen.hmac_key' => null]);
    $order = adyenUnpaidOrder();
    $payment = adyenPendingPayment($order);

    $this->postJson($this->baseUrl('/webhook/adyen'), adyenDelivery(adyenAuthItem((string) $payment->id)))
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});
