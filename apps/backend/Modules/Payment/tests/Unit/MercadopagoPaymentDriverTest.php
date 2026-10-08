<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\Drivers\MercadopagoPaymentDriver;
use Modules\Payment\Gateways\MercadopagoClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(HelperTrait::class);

beforeEach(function () {
    config([
        'app.name' => 'Baxela',
        'payment.mercadopago.access_token' => 'MP-ACCESS-TOKEN',
        'payment.mercadopago.webhook_secret' => 'mp-webhook-test',
        'payment.mercadopago.sandbox' => true,
        'payment.mercadopago.notification_url' => 'https://api.baxela.test/api/v1/payment/webhook/mercadopago',
        'payment.mercadopago.success_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
        'payment.mercadopago.pending_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
        'payment.mercadopago.failure_url' => 'https://shop.test/en/payment/return?order_code={order_code}&status=cancel',
    ]);
});

/**
 * Driver wired to a mocked MercadopagoClient; $configureClient sets the
 * API expectations (and can capture the request params).
 */
function mercadopagoDriver(callable $configureClient): MercadopagoPaymentDriver
{
    $client = Mockery::mock(MercadopagoClient::class);
    $configureClient($client);

    return new MercadopagoPaymentDriver($client);
}

/**
 * Partial mock: the real verifyWebhookSignature runs (a pure computation
 * over the manifest), only the payment fetch is stubbed.
 */
function mercadopagoFetchingDriver(object $payment): MercadopagoPaymentDriver
{
    $client = Mockery::mock(MercadopagoClient::class)->makePartial();
    $client->shouldReceive('getPayment')->once()->andReturn($payment);

    return new MercadopagoPaymentDriver($client);
}

/**
 * Same partial, for notifications that must be rejected before any fetch:
 * the real signature verification runs, the payment is never fetched.
 */
function mercadopagoRejectingDriver(): MercadopagoPaymentDriver
{
    $client = Mockery::mock(MercadopagoClient::class)->makePartial();
    $client->shouldReceive('getPayment')->never();

    return new MercadopagoPaymentDriver($client);
}

/**
 * Real signed shadow notification — exercises the actual x-signature
 * verification path. $signDataId (defaults to $dataId) lets tests tamper
 * with the delivered id while signing the original; $sign = false omits
 * the header entirely.
 */
function mercadopagoNotification(string $dataId, ?string $signDataId = null, bool $sign = true, string $secret = 'mp-webhook-test'): Request
{
    $headers = ['HTTP_X_REQUEST_ID' => 'req_1'];

    if ($sign) {
        $headers['HTTP_X_SIGNATURE'] = mercadopagoSignature($signDataId ?? $dataId, 'req_1', $secret);
    }

    $body = json_encode(['type' => 'payment', 'data' => ['id' => $dataId]], JSON_THROW_ON_ERROR);

    return Request::create('/api/v1/payment/webhook/mercadopago', 'POST', [], [], [], $headers, $body);
}

/**
 * Mercado Pago's documented signing recipe, mirrored here so the driver
 * is verified against independently computed signatures: hex HMAC-SHA256
 * over id:{data.id};request-id:{x-request-id};ts:{ts};
 */
function mercadopagoSignature(string $dataId, string $requestId, string $secret, string $timestamp = '1762300000'): string
{
    $manifest = "id:{$dataId};request-id:{$requestId};ts:{$timestamp};";

    return 'ts='.$timestamp.',v1='.hash_hmac('sha256', $manifest, $secret);
}

/**
 * Payment resource as GET /v1/payments/{id} returns it.
 */
function mercadopagoPaymentResource(string $status, string $reference = '9'): object
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
 * Preference response: the driver only reads the redirect URLs.
 */
function mercadopagoPreferenceResponse(): object
{
    return (object) [
        'id' => 'pref_1',
        'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_1',
        'sandbox_init_point' => 'https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_1',
    ];
}

it('creates a preference with a decimal item, reference and back urls', function () {
    $captured = [];
    $driver = mercadopagoDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createPreference')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(mercadopagoPreferenceResponse());
    });

    $result = $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'mercadopago',
        order_code: 'ABCD2345',
        currency: 'BRL',
        currency_decimal_places: 2,
    ));

    expect($result->redirect_url)->toBe('https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_1')
        ->and($result->transaction_id)->toBe('9')
        ->and($captured['external_reference'])->toBe('9')
        ->and($captured['items'])->toBe([[
            'title' => 'Baxela ABCD2345',
            'quantity' => 1,
            'unit_price' => 305.0,
            'currency_id' => 'BRL',
        ]])
        ->and($captured['back_urls'])->toBe([
            'success' => 'https://shop.test/en/payment/return?order_code=ABCD2345',
            'pending' => 'https://shop.test/en/payment/return?order_code=ABCD2345',
            'failure' => 'https://shop.test/en/payment/return?order_code=ABCD2345&status=cancel',
        ])
        ->and($captured['notification_url'])->toBe('https://api.baxela.test/api/v1/payment/webhook/mercadopago');
});

it('prefers the live init point when sandbox is off', function () {
    config(['payment.mercadopago.sandbox' => false]);

    $driver = mercadopagoDriver(function (MockInterface $client): void {
        $client->shouldReceive('createPreference')->andReturn(mercadopagoPreferenceResponse());
    });

    $result = $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'mercadopago',
        order_code: 'ABCD2345',
        currency: 'BRL',
        currency_decimal_places: 2,
    ));

    expect($result->redirect_url)->toBe('https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_1');
});

it('respects zero-decimal currencies', function () {
    $captured = [];
    $driver = mercadopagoDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createPreference')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(mercadopagoPreferenceResponse());
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'mercadopago',
        order_code: 'ABCD2345',
        currency: 'CLP',
        currency_decimal_places: 0,
    ));

    expect($captured['items'][0]['unit_price'])->toBe(305.0)
        ->and($captured['items'][0]['currency_id'])->toBe('CLP');
});

it('omits the notification url when not configured', function () {
    config(['payment.mercadopago.notification_url' => null]);

    $captured = [];
    $driver = mercadopagoDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createPreference')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(mercadopagoPreferenceResponse());
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'mercadopago',
        order_code: 'ABCD2345',
        currency: 'BRL',
        currency_decimal_places: 2,
    ));

    expect($captured)->not->toHaveKey('notification_url');
});

it('refuses to initiate when the gateway is not configured', function () {
    config(['payment.mercadopago.access_token' => null]);
    $driver = new MercadopagoPaymentDriver(app(MercadopagoClient::class));

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'mercadopago',
        order_code: 'ABCD2345',
        currency: 'BRL',
        currency_decimal_places: 2,
    ));
})->throws(PaymentException::class);

it('fails loudly when the preference response is missing its redirect url', function () {
    $driver = mercadopagoDriver(function (MockInterface $client): void {
        $client->shouldReceive('createPreference')
            ->andReturn((object) ['id' => 'pref_1']);
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'mercadopago',
        order_code: 'ABCD2345',
        currency: 'BRL',
        currency_decimal_places: 2,
    ));
})->throws(RuntimeException::class);

it('refuses webhooks when the webhook secret is not configured', function () {
    config(['payment.mercadopago.webhook_secret' => null]);

    new MercadopagoPaymentDriver(app(MercadopagoClient::class))
        ->handleWebhook(mercadopagoNotification('123456789'));
})->throws(PaymentException::class);

it('settles an approved payment notification', function () {
    $result = mercadopagoFetchingDriver(mercadopagoPaymentResource('approved'))
        ->handleWebhook(mercadopagoNotification('123456789'));

    expect($result->transaction_id)->toBe('9')
        ->and($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('maps terminal failure statuses', function (string $status) {
    $result = mercadopagoFetchingDriver(mercadopagoPaymentResource($status))
        ->handleWebhook(mercadopagoNotification('123456789'));

    expect($result->status)->toBe(PaymentStatusEnum::FAILED->value);
})->with([
    'rejected' => ['rejected'],
    'cancelled' => ['cancelled'],
]);

it('rejects non-terminal payment statuses so the payment stays pending', function (string $status) {
    mercadopagoFetchingDriver(mercadopagoPaymentResource($status))
        ->handleWebhook(mercadopagoNotification('123456789'));
})->throws(PaymentException::class)->with([
    'pending' => ['pending'],
    'in process' => ['in_process'],
    'authorized' => ['authorized'],
    'refunded' => ['refunded'],
]);

it('accepts the older query-string notification style', function () {
    $result = mercadopagoFetchingDriver(mercadopagoPaymentResource('approved'))
        ->handleWebhook(Request::create('/api/v1/payment/webhook/mercadopago?data.id=123456789', 'POST', [], [], [], [
            'HTTP_X_REQUEST_ID' => 'req_1',
            'HTTP_X_SIGNATURE' => mercadopagoSignature('123456789', 'req_1', 'mp-webhook-test'),
        ], ''));

    expect($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('rejects notifications signed for a different payment', function () {
    mercadopagoRejectingDriver()
        ->handleWebhook(mercadopagoNotification('123456789', signDataId: '999'));
})->throws(PaymentException::class);

it('rejects a notification without a signature', function () {
    mercadopagoRejectingDriver()
        ->handleWebhook(mercadopagoNotification('123456789', sign: false));
})->throws(PaymentException::class);

it('rejects a notification signed with the wrong secret', function () {
    mercadopagoRejectingDriver()
        ->handleWebhook(mercadopagoNotification('123456789', secret: 'mp-webhook-other'));
})->throws(PaymentException::class);

it('rejects a payment resource with an empty external reference', function () {
    mercadopagoFetchingDriver(mercadopagoPaymentResource('approved', ''))
        ->handleWebhook(mercadopagoNotification('123456789'));
})->throws(PaymentException::class);

it('rejects a notification without a payment id', function () {
    $body = json_encode(['type' => 'payment'], JSON_THROW_ON_ERROR);

    new MercadopagoPaymentDriver(app(MercadopagoClient::class))
        ->handleWebhook(Request::create('/api/v1/payment/webhook/mercadopago', 'POST', [], [], [], [], $body));
})->throws(PaymentException::class);

it('rejects a notification whose body is not valid json', function () {
    new MercadopagoPaymentDriver(app(MercadopagoClient::class))
        ->handleWebhook(Request::create('/api/v1/payment/webhook/mercadopago', 'POST', [], [], [], [], 'not-json'));
})->throws(PaymentException::class);

it('bubbles payment fetch failures so mercado pago retries the webhook', function () {
    $client = Mockery::mock(MercadopagoClient::class)->makePartial();
    $client->shouldReceive('getPayment')
        ->andThrow(new RequestException(new Response(new Psr7Response(503, [], 'unavailable'))));

    (new MercadopagoPaymentDriver($client))
        ->handleWebhook(mercadopagoNotification('123456789'));
})->throws(RequestException::class);
