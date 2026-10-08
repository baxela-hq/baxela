<?php

use Illuminate\Http\Request;
use Mockery\MockInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\CheckoutcomClient;
use Modules\Payment\Gateways\Drivers\CheckoutcomPaymentDriver;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(HelperTrait::class);

beforeEach(function () {
    config([
        'payment.checkoutcom.secret_key' => 'sk_test_x',
        'payment.checkoutcom.webhook_secret' => 'cko-webhook-test',
        'payment.checkoutcom.return_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
    ]);
});

/**
 * Driver wired to a mocked CheckoutcomClient; $configureClient sets the
 * API expectations (and can capture the request params).
 */
function checkoutcomDriver(callable $configureClient): CheckoutcomPaymentDriver
{
    $client = Mockery::mock(CheckoutcomClient::class);
    $configureClient($client);

    return new CheckoutcomPaymentDriver($client);
}

/**
 * Payment-link response: the driver only reads the hosted URL from the
 * relation-keyed _links map.
 */
function checkoutcomLinkResponse(): object
{
    return (object) [
        'id' => 'plink_w2ujbp3y4sbu5gqwn5ja2y5xtu',
        '_links' => (object) [
            'payment-link' => (object) ['href' => 'https://pay.checkout.com/link/plink_w2ujbp3y4sbu5gqwn5ja2y5xtu'],
        ],
    ];
}

/**
 * Default captured event for payment 9.
 */
function checkoutcomEventPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'evt_jclzjykecuuu7mmesbk63alzoa',
        'type' => 'payment_captured',
        'data' => [
            'id' => 'pay_mbabxz242c5syclbu3swqk43ea',
            'reference' => '9',
            'status' => 'Captured',
            'amount' => 30500,
            'currency' => 'USD',
        ],
    ], $overrides);
}

/**
 * Real signed webhook delivery — exercises the actual raw-body HMAC
 * verification path. $rawBodyOverride delivers different bytes than the
 * ones signed, simulating post-signing tampering; $sign = false omits the
 * header entirely.
 */
function checkoutcomEvent(array $payload, ?string $rawBodyOverride = null, bool $sign = true, string $secret = 'cko-webhook-test'): Request
{
    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
    $headers = [];

    if ($sign) {
        $headers['HTTP_CKO_SIGNATURE'] = checkoutcomSignature($rawBodyOverride ?? $rawBody, $secret);
    }

    return Request::create('/api/v1/payment/webhook/checkoutcom', 'POST', [], [], [], $headers, $rawBody);
}

/**
 * Checkout.com's documented signing recipe, mirrored here so the driver
 * is verified against independently computed signatures: hex HMAC-SHA256
 * over the exact raw body bytes.
 */
function checkoutcomSignature(string $rawBody, string $secret): string
{
    return hash_hmac('sha256', $rawBody, $secret);
}

it('creates a payment link with minor units, reference and return url', function () {
    $captured = [];
    $driver = checkoutcomDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createPaymentLink')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(checkoutcomLinkResponse());
    });

    $result = $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'checkoutcom',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));

    expect($result->redirect_url)->toBe('https://pay.checkout.com/link/plink_w2ujbp3y4sbu5gqwn5ja2y5xtu')
        ->and($result->transaction_id)->toBe('9')
        ->and($captured['reference'])->toBe('9')
        ->and($captured['amount'])->toBe(30500)
        ->and($captured['currency'])->toBe('USD')
        ->and($captured['return_url'])->toBe('https://shop.test/en/payment/return?order_code=ABCD2345');
});

it('respects zero-decimal currencies', function () {
    $captured = [];
    $driver = checkoutcomDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createPaymentLink')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(checkoutcomLinkResponse());
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'checkoutcom',
        order_code: 'ABCD2345',
        currency: 'JPY',
        currency_decimal_places: 0,
    ));

    expect($captured['amount'])->toBe(305);
});

it('refuses to initiate when the gateway is not configured', function () {
    config(['payment.checkoutcom.secret_key' => null]);
    $driver = new CheckoutcomPaymentDriver(app(CheckoutcomClient::class));

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'checkoutcom',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));
})->throws(PaymentException::class);

it('fails loudly when the link response is missing its hosted url', function (array $links) {
    $driver = checkoutcomDriver(function (MockInterface $client) use ($links): void {
        $client->shouldReceive('createPaymentLink')
            ->andReturn((object) ['id' => 'plink_w2ujbp3y4sbu5gqwn5ja2y5xtu', '_links' => (object) $links]);
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'checkoutcom',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));
})->throws(RuntimeException::class)->with([
    'no links at all' => [[]],
    'unrelated relation' => [['self' => (object) ['href' => 'https://api.checkout.com/payment-links/plink_x']]],
]);

it('refuses webhooks when the webhook secret is not configured', function () {
    config(['payment.checkoutcom.webhook_secret' => null]);

    new CheckoutcomPaymentDriver(app(CheckoutcomClient::class))
        ->handleWebhook(checkoutcomEvent(checkoutcomEventPayload()));
})->throws(PaymentException::class);

it('settles a captured event', function () {
    $result = new CheckoutcomPaymentDriver(app(CheckoutcomClient::class))
        ->handleWebhook(checkoutcomEvent(checkoutcomEventPayload()));

    expect($result->transaction_id)->toBe('9')
        ->and($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('maps terminal failure events', function (string $type) {
    $result = new CheckoutcomPaymentDriver(app(CheckoutcomClient::class))
        ->handleWebhook(checkoutcomEvent(checkoutcomEventPayload(['type' => $type])));

    expect($result->status)->toBe(PaymentStatusEnum::FAILED->value);
})->with([
    'declined' => ['payment_declined'],
    'capture declined' => ['payment_capture_declined'],
    'canceled' => ['payment_canceled'],
    'expired' => ['payment_expired'],
]);

it('rejects non-terminal events so the payment stays pending', function (string $type) {
    new CheckoutcomPaymentDriver(app(CheckoutcomClient::class))
        ->handleWebhook(checkoutcomEvent(checkoutcomEventPayload(['type' => $type])));
})->throws(PaymentException::class)->with([
    'approved is authorised, not captured' => ['payment_approved'],
    'refunded is out of scope' => ['payment_refunded'],
]);

it('rejects a webhook whose body was modified after signing', function () {
    $tampered = json_encode(checkoutcomEventPayload(['data' => ['id' => 'pay_other', 'reference' => '10', 'status' => 'Captured', 'amount' => 1, 'currency' => 'USD']]), JSON_THROW_ON_ERROR);

    new CheckoutcomPaymentDriver(app(CheckoutcomClient::class))
        ->handleWebhook(checkoutcomEvent(checkoutcomEventPayload(), rawBodyOverride: $tampered));
})->throws(PaymentException::class);

it('rejects a webhook signed with the wrong secret', function () {
    new CheckoutcomPaymentDriver(app(CheckoutcomClient::class))
        ->handleWebhook(checkoutcomEvent(checkoutcomEventPayload(), secret: 'cko-webhook-other'));
})->throws(PaymentException::class);

it('rejects a webhook without a signature', function () {
    new CheckoutcomPaymentDriver(app(CheckoutcomClient::class))
        ->handleWebhook(checkoutcomEvent(checkoutcomEventPayload(), sign: false));
})->throws(PaymentException::class);

it('rejects an event with an empty reference', function () {
    new CheckoutcomPaymentDriver(app(CheckoutcomClient::class))
        ->handleWebhook(checkoutcomEvent(checkoutcomEventPayload(['data' => ['id' => 'pay_mbabxz242c5syclbu3swqk43ea', 'reference' => '', 'status' => 'Captured', 'amount' => 30500, 'currency' => 'USD']])));
})->throws(PaymentException::class);

it('rejects a webhook whose body is not valid json even when correctly signed', function () {
    $rawBody = 'not-json';

    new CheckoutcomPaymentDriver(app(CheckoutcomClient::class))
        ->handleWebhook(Request::create('/api/v1/payment/webhook/checkoutcom', 'POST', [], [], [], [
            'HTTP_CKO_SIGNATURE' => checkoutcomSignature($rawBody, 'cko-webhook-test'),
        ], $rawBody));
})->throws(PaymentException::class);
