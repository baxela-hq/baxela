<?php

use Illuminate\Http\Request;
use Mockery\MockInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\Drivers\StripePaymentDriver;
use Modules\Payment\Gateways\StripeCheckout;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;
use Stripe\Checkout\Session;

uses(HelperTrait::class);

beforeEach(function () {
    config([
        'payment.stripe.secret' => 'sk_test_x',
        'payment.stripe.webhook_secret' => 'whsec_test',
        'payment.stripe.success_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
        'payment.stripe.cancel_url' => 'https://shop.test/en/payment/return?order_code={order_code}&status=cancel',
    ]);
});

/**
 * Driver wired to a mocked StripeCheckout; $configureCheckout sets the
 * session-creation expectation (and can capture the params).
 */
function stripeDriver(callable $configureCheckout): StripePaymentDriver
{
    $checkout = Mockery::mock(StripeCheckout::class);
    $configureCheckout($checkout);

    return new StripePaymentDriver($checkout);
}

/**
 * Real signed webhook request — exercises the actual HMAC verification path.
 */
function signedStripeEvent(array $payload, ?string $secret = 'whsec_test', ?string $signature = null): Request
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature ??= sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', "{$timestamp}.{$body}", $secret));

    return Request::create('/api/v1/payment/webhook/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $body);
}

it('creates a checkout session with minor units, order metadata and return urls', function () {
    $captured = [];
    $driver = stripeDriver(function (MockInterface $checkout) use (&$captured): void {
        $checkout->shouldReceive('createSession')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(Session::constructFrom([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/pay/cs_test_123',
            ]));
    });

    $result = $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'stripe',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));

    $priceData = $captured['line_items'][0]['price_data'];

    expect($result->redirect_url)->toBe('https://checkout.stripe.com/pay/cs_test_123')
        ->and($result->transaction_id)->toBe('cs_test_123')
        ->and($captured['mode'])->toBe('payment')
        ->and($priceData['unit_amount'])->toBe(30500)
        ->and($priceData['currency'])->toBe('usd')
        ->and($captured['client_reference_id'])->toBe('9')
        ->and($captured['metadata']['payment_id'])->toBe('9')
        ->and($captured['metadata']['order_id'])->toBe('4')
        ->and($captured['success_url'])->toBe('https://shop.test/en/payment/return?order_code=ABCD2345')
        ->and($captured['cancel_url'])->toBe('https://shop.test/en/payment/return?order_code=ABCD2345&status=cancel');
});

it('respects zero-decimal currencies', function () {
    $captured = [];
    $driver = stripeDriver(function (MockInterface $checkout) use (&$captured): void {
        $checkout->shouldReceive('createSession')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(Session::constructFrom(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/pay/cs_test_1']));
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'stripe',
        order_code: 'ABCD2345',
        currency: 'JPY',
        currency_decimal_places: 0,
    ));

    expect($captured['line_items'][0]['price_data']['unit_amount'])->toBe(305);
});

it('refuses to initiate when the gateway is not configured', function () {
    config(['payment.stripe.secret' => null]);
    $driver = new StripePaymentDriver(app(StripeCheckout::class));

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'stripe',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));
})->throws(PaymentException::class);

it('refuses webhooks when the signing secret is not configured', function () {
    config(['payment.stripe.webhook_secret' => null]);
    $driver = new StripePaymentDriver(app(StripeCheckout::class));

    $driver->handleWebhook(signedStripeEvent([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_123', 'payment_status' => 'paid']],
    ]));
})->throws(PaymentException::class);

it('maps a paid completed session webhook to success', function () {
    $driver = new StripePaymentDriver(app(StripeCheckout::class));

    $result = $driver->handleWebhook(signedStripeEvent([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_123', 'payment_status' => 'paid']],
    ]));

    expect($result->transaction_id)->toBe('cs_test_123')
        ->and($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('maps deferred payment outcome events', function (string $type, string $expectedStatus) {
    $driver = new StripePaymentDriver(app(StripeCheckout::class));

    $result = $driver->handleWebhook(signedStripeEvent([
        'id' => 'evt_1',
        'type' => $type,
        'data' => ['object' => ['id' => 'cs_test_123']],
    ]));

    expect($result->status)->toBe($expectedStatus);
})->with([
    'async succeeded' => ['checkout.session.async_payment_succeeded', 'success'],
    'async failed' => ['checkout.session.async_payment_failed', 'failed'],
    'expired' => ['checkout.session.expired', 'failed'],
]);

it('rejects a completed-but-unpaid session so the payment stays pending', function () {
    $driver = new StripePaymentDriver(app(StripeCheckout::class));

    $driver->handleWebhook(signedStripeEvent([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_123', 'payment_status' => 'unpaid']],
    ]));
})->throws(PaymentException::class);

it('rejects webhook events it cannot settle', function (array $payload) {
    $driver = new StripePaymentDriver(app(StripeCheckout::class));

    $driver->handleWebhook(signedStripeEvent($payload));
})->throws(PaymentException::class)->with([
    'unmapped event type' => [[
        'id' => 'evt_1',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_1']],
    ]],
    'payload without a session object' => [[
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
    ]],
]);

it('rejects a webhook whose body is not valid json', function () {
    $driver = new StripePaymentDriver(app(StripeCheckout::class));

    $body = 'not-json';
    $timestamp = time();
    $signature = sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_test'));

    $driver->handleWebhook(Request::create('/api/v1/payment/webhook/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $body));
})->throws(PaymentException::class);

it('rejects a webhook with a bad signature', function () {
    $driver = new StripePaymentDriver(app(StripeCheckout::class));

    $driver->handleWebhook(signedStripeEvent(
        ['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_123', 'payment_status' => 'paid']]],
        secret: 'whsec_other',
    ));
})->throws(PaymentException::class);
