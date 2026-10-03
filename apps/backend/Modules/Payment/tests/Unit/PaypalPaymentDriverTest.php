<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\Drivers\PaypalPaymentDriver;
use Modules\Payment\Gateways\PaypalClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(HelperTrait::class);

beforeEach(function () {
    config([
        'payment.paypal.client_id' => 'cid_test',
        'payment.paypal.client_secret' => 'secret_test',
        'payment.paypal.webhook_id' => 'wh_test',
        'payment.paypal.return_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
        'payment.paypal.cancel_url' => 'https://shop.test/en/payment/return?order_code={order_code}&status=cancel',
    ]);
});

/**
 * Driver wired to a mocked PaypalClient; $configureClient sets the API
 * expectations (and can capture the request params).
 */
function paypalDriver(callable $configureClient): PaypalPaymentDriver
{
    $client = Mockery::mock(PaypalClient::class);
    $configureClient($client);

    return new PaypalPaymentDriver($client);
}

/**
 * Webhook request carrying the PAYPAL-* transmission headers the
 * certificate-based signature verification needs.
 */
function paypalEvent(string $type, array $resource): Request
{
    $body = json_encode(['id' => 'evt_1', 'event_type' => $type, 'resource' => $resource], JSON_THROW_ON_ERROR);

    return Request::create('/api/v1/payment/webhook/paypal', 'POST', [], [], [], [
        'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
        'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert',
        'HTTP_PAYPAL_TRANSMISSION_ID' => 'tid_1',
        'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig_1',
        'HTTP_PAYPAL_TRANSMISSION_TIME' => '2026-10-03T00:00:00Z',
    ], $body);
}

/**
 * Order-create API response: the driver only reads id + the approve link.
 */
function paypalOrderResponse(): object
{
    return (object) [
        'id' => '5O190127TN3647153',
        'links' => [
            (object) ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/5O190127TN3647153'],
            (object) ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN3647153'],
        ],
    ];
}

/**
 * Capture API response embedding the capture result under purchase units.
 */
function paypalCaptureResponse(string $status): object
{
    return (object) [
        'id' => '5O190127TN3647153',
        'status' => 'COMPLETED',
        'purchase_units' => [
            (object) ['payments' => (object) ['captures' => [(object) ['id' => '41W53218KT3647153', 'status' => $status]]]],
        ],
    ];
}

it('creates an order with a decimal amount, order metadata and return urls', function () {
    $captured = [];
    $driver = paypalDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createOrder')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(paypalOrderResponse());
    });

    $result = $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'paypal',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));

    expect($result->redirect_url)->toBe('https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN3647153')
        ->and($result->transaction_id)->toBe('5O190127TN3647153')
        ->and($captured['intent'])->toBe('CAPTURE')
        ->and($captured['purchase_units'][0]['reference_id'])->toBe('ABCD2345')
        ->and($captured['purchase_units'][0]['custom_id'])->toBe('9')
        ->and($captured['purchase_units'][0]['amount'])->toBe(['currency_code' => 'USD', 'value' => '305.00'])
        ->and($captured['application_context']['return_url'])->toBe('https://shop.test/en/payment/return?order_code=ABCD2345')
        ->and($captured['application_context']['cancel_url'])->toBe('https://shop.test/en/payment/return?order_code=ABCD2345&status=cancel');
});

it('respects zero-decimal currencies', function () {
    $captured = [];
    $driver = paypalDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createOrder')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(paypalOrderResponse());
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'paypal',
        order_code: 'ABCD2345',
        currency: 'JPY',
        currency_decimal_places: 0,
    ));

    expect($captured['purchase_units'][0]['amount']['value'])->toBe('305');
});

it('refuses to initiate when the gateway is not configured', function () {
    config(['payment.paypal.client_id' => null]);
    $driver = new PaypalPaymentDriver(app(PaypalClient::class));

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'paypal',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));
})->throws(PaymentException::class);

it('refuses webhooks when the webhook id is not configured', function () {
    config(['payment.paypal.webhook_id' => null]);

    paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')->never();
    })->handleWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => '5O190127TN3647153']));
})->throws(PaymentException::class);

it('captures and settles an approved order webhook', function () {
    $driver = paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')
            ->once()
            ->with(
                Mockery::on(fn (array $event): bool => $event['event_type'] === 'CHECKOUT.ORDER.APPROVED'),
                Mockery::on(fn (array $transmission): bool => $transmission['transmission_id'] === 'tid_1'
                    && $transmission['auth_algo'] === 'SHA256withRSA'
                    && $transmission['cert_url'] === 'https://api.paypal.com/cert'
                    && $transmission['transmission_sig'] === 'sig_1'
                    && $transmission['transmission_time'] === '2026-10-03T00:00:00Z'),
            )
            ->andReturnTrue();
        $client->shouldReceive('captureOrder')
            ->once()
            ->with('5O190127TN3647153')
            ->andReturn(paypalCaptureResponse('COMPLETED'));
    });

    $result = $driver->handleWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => '5O190127TN3647153']));

    expect($result->transaction_id)->toBe('5O190127TN3647153')
        ->and($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('marks an approved order failed when the capture is denied', function () {
    $driver = paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')->andReturnTrue();
        $client->shouldReceive('captureOrder')->andReturn(paypalCaptureResponse('DENIED'));
    });

    $result = $driver->handleWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => '5O190127TN3647153']));

    expect($result->status)->toBe(PaymentStatusEnum::FAILED->value);
});

it('treats a replayed approval after settlement as already captured', function () {
    $driver = paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')->andReturnTrue();
        $client->shouldReceive('captureOrder')
            ->andThrow(new RequestException(new Response(new Psr7Response(422, [], (string) json_encode([
                'name' => 'UNPROCESSABLE_ENTITY',
                'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']],
            ])))));
    });

    $result = $driver->handleWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => '5O190127TN3647153']));

    expect($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('rethrows capture failures so paypal retries the webhook', function () {
    $driver = paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')->andReturnTrue();
        $client->shouldReceive('captureOrder')
            ->andThrow(new RequestException(new Response(new Psr7Response(503, [], 'unavailable'))));
    });

    $driver->handleWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => '5O190127TN3647153']));
})->throws(RequestException::class);

it('maps terminal capture events without capturing again', function (string $type, string $expectedStatus) {
    $driver = paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')->andReturnTrue();
        $client->shouldReceive('captureOrder')->never();
    });

    $result = $driver->handleWebhook(paypalEvent($type, [
        'id' => '41W53218KT3647153',
        'supplementary_data' => ['related_ids' => ['order_id' => '5O190127TN3647153']],
    ]));

    expect($result->transaction_id)->toBe('5O190127TN3647153')
        ->and($result->status)->toBe($expectedStatus);
})->with([
    'capture completed' => ['PAYMENT.CAPTURE.COMPLETED', 'success'],
    'capture denied' => ['PAYMENT.CAPTURE.DENIED', 'failed'],
]);

it('maps an approval reversal to failed', function () {
    $driver = paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')->andReturnTrue();
    });

    $result = $driver->handleWebhook(paypalEvent('CHECKOUT.PAYMENT-APPROVAL.REVERSED', ['id' => '5O190127TN3647153']));

    expect($result->transaction_id)->toBe('5O190127TN3647153')
        ->and($result->status)->toBe(PaymentStatusEnum::FAILED->value);
});

it('rejects webhook events it cannot settle', function (array $resource, ?string $type = 'PAYMENT.CAPTURE.REFUNDED') {
    $driver = paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')->andReturnTrue();
    });

    $driver->handleWebhook(paypalEvent($type, $resource));
})->throws(PaymentException::class)->with([
    'unmapped event type' => [['id' => '41W53218KT3647153']],
    'capture event without an order id' => [['id' => '41W53218KT3647153'], 'PAYMENT.CAPTURE.COMPLETED'],
    'order event without an id' => [[], 'CHECKOUT.ORDER.APPROVED'],
]);

it('rejects a webhook whose body is not valid json', function () {
    $driver = paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')->never();
    });

    $driver->handleWebhook(Request::create('/api/v1/payment/webhook/paypal', 'POST', [], [], [], [], 'not-json'));
})->throws(PaymentException::class);

it('rejects a webhook that fails signature verification', function () {
    $driver = paypalDriver(function (MockInterface $client): void {
        $client->shouldReceive('verifyWebhookSignature')->andReturnFalse();
    });

    $driver->handleWebhook(paypalEvent('PAYMENT.CAPTURE.COMPLETED', [
        'id' => '41W53218KT3647153',
        'supplementary_data' => ['related_ids' => ['order_id' => '5O190127TN3647153']],
    ]));
})->throws(PaymentException::class);
