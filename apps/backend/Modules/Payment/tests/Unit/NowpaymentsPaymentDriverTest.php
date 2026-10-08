<?php

use Illuminate\Http\Request;
use Mockery\MockInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\Drivers\NowpaymentsPaymentDriver;
use Modules\Payment\Gateways\NowpaymentsClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(HelperTrait::class);

beforeEach(function () {
    config([
        'app.name' => 'Baxela',
        'payment.nowpayments.api_key' => 'NP-API-KEY',
        'payment.nowpayments.ipn_secret' => 'np-ipn-test',
        'payment.nowpayments.ipn_callback_url' => 'https://api.baxela.test/api/v1/payment/webhook/nowpayments',
        'payment.nowpayments.success_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
        'payment.nowpayments.cancel_url' => 'https://shop.test/en/payment/return?order_code={order_code}&status=cancel',
    ]);
});

/**
 * Driver wired to a mocked NowpaymentsClient; $configureClient sets the
 * API expectations (and can capture the request params).
 */
function nowpaymentsDriver(callable $configureClient): NowpaymentsPaymentDriver
{
    $client = Mockery::mock(NowpaymentsClient::class);
    $configureClient($client);

    return new NowpaymentsPaymentDriver($client);
}

/**
 * Default finished IPN for payment 9; URL-ish fields exercise the
 * unescaped-slashes re-encoding the signature depends on.
 */
function nowpaymentsIpn(array $overrides = []): array
{
    return array_merge([
        'payment_id' => 4607606000,
        'invoice_id' => 4607606111,
        'order_id' => '9',
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
 * Real signed IPN delivery — exercises the actual HMAC-SHA512
 * verification path. $signIpn (defaults to $ipn) lets tests tamper with
 * the delivered body while signing the original; $sign = false omits the
 * signature header entirely.
 */
function nowpaymentsSignedRequest(array $ipn, ?array $signIpn = null, bool $sign = true, string $secret = 'np-ipn-test'): Request
{
    $headers = [];

    if ($sign) {
        $headers['HTTP_X_NOWPAYMENTS_SIG'] = nowpaymentsSignature($signIpn ?? $ipn, $secret);
    }

    return Request::create('/api/v1/payment/webhook/nowpayments', 'POST', [], [], [], $headers, json_encode($ipn, JSON_THROW_ON_ERROR));
}

/**
 * NowPayments' documented signing recipe, mirrored here so the driver is
 * verified against independently computed signatures: HMAC-SHA512 over
 * the body re-serialized with keys sorted alphabetically (recursively),
 * without escaping slashes or unicode.
 */
function nowpaymentsSignature(array $ipn, string $secret): string
{
    return hash_hmac(
        'sha512',
        json_encode(nowpaymentsSortKeys($ipn), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $secret,
    );
}

/**
 * @param  array<string, mixed>  $value
 * @return array<string, mixed>
 */
function nowpaymentsSortKeys(array $value): array
{
    ksort($value);

    foreach ($value as &$item) {
        if (is_array($item)) {
            $item = nowpaymentsSortKeys($item);
        }
    }

    return $value;
}

it('creates an invoice with a decimal price, order reference and return urls', function () {
    $captured = [];
    $driver = nowpaymentsDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createInvoice')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn((object) [
                'id' => 4607606111,
                'invoice_url' => 'https://nowpayments.io/pay/i4607606111',
                'status' => 'waiting',
            ]);
    });

    $result = $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'nowpayments',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));

    expect($result->redirect_url)->toBe('https://nowpayments.io/pay/i4607606111')
        ->and($result->transaction_id)->toBe('9')
        ->and($captured['order_id'])->toBe('9')
        ->and($captured['order_description'])->toBe('Baxela ABCD2345')
        ->and($captured['price_amount'])->toBe(305.0)
        ->and($captured['price_currency'])->toBe('usd')
        ->and($captured['success_url'])->toBe('https://shop.test/en/payment/return?order_code=ABCD2345')
        ->and($captured['cancel_url'])->toBe('https://shop.test/en/payment/return?order_code=ABCD2345&status=cancel')
        ->and($captured['ipn_callback_url'])->toBe('https://api.baxela.test/api/v1/payment/webhook/nowpayments');
});

it('respects zero-decimal currencies', function () {
    $captured = [];
    $driver = nowpaymentsDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createInvoice')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn((object) [
                'id' => 4607606111,
                'invoice_url' => 'https://nowpayments.io/pay/i4607606111',
                'status' => 'waiting',
            ]);
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'nowpayments',
        order_code: 'ABCD2345',
        currency: 'JPY',
        currency_decimal_places: 0,
    ));

    expect($captured['price_amount'])->toBe(305.0)
        ->and($captured['price_currency'])->toBe('jpy');
});

it('omits the ipn callback when not configured', function () {
    config(['payment.nowpayments.ipn_callback_url' => null]);

    $captured = [];
    $driver = nowpaymentsDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createInvoice')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn((object) [
                'id' => 4607606111,
                'invoice_url' => 'https://nowpayments.io/pay/i4607606111',
                'status' => 'waiting',
            ]);
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'nowpayments',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));

    expect($captured)->not->toHaveKey('ipn_callback_url');
});

it('refuses to initiate when the gateway is not configured', function () {
    config(['payment.nowpayments.api_key' => null]);
    $driver = new NowpaymentsPaymentDriver(app(NowpaymentsClient::class));

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'nowpayments',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));
})->throws(PaymentException::class);

it('fails loudly when the invoice response is missing its url', function () {
    $driver = nowpaymentsDriver(function (MockInterface $client): void {
        $client->shouldReceive('createInvoice')
            ->andReturn((object) ['id' => 4607606111, 'status' => 'waiting']);
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'nowpayments',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));
})->throws(RuntimeException::class);

it('refuses webhooks when the ipn secret is not configured', function () {
    config(['payment.nowpayments.ipn_secret' => null]);

    new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(nowpaymentsSignedRequest(nowpaymentsIpn()));
})->throws(PaymentException::class);

it('settles a finished ipn', function () {
    $result = new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(nowpaymentsSignedRequest(nowpaymentsIpn()));

    expect($result->transaction_id)->toBe('9')
        ->and($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('maps terminal failure statuses', function (string $status) {
    $result = new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(nowpaymentsSignedRequest(nowpaymentsIpn(['payment_status' => $status])));

    expect($result->status)->toBe(PaymentStatusEnum::FAILED->value);
})->with([
    'failed' => ['failed'],
    'expired' => ['expired'],
]);

it('rejects non-terminal ipns so the payment stays pending', function (string $status) {
    new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(nowpaymentsSignedRequest(nowpaymentsIpn(['payment_status' => $status])));
})->throws(PaymentException::class)->with([
    'waiting' => ['waiting'],
    'confirming' => ['confirming'],
    'confirmed' => ['confirmed'],
    'sending' => ['sending'],
    'partially paid' => ['partially_paid'],
    'refunded' => ['refunded'],
]);

it('rejects ipns whose signed fields were tampered with', function (array $tampered) {
    new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(nowpaymentsSignedRequest(nowpaymentsIpn($tampered), signIpn: nowpaymentsIpn()));
})->throws(PaymentException::class)->with([
    'order reference' => [['order_id' => '10']],
    'payment status' => [['payment_status' => 'failed']],
    'price amount' => [['price_amount' => 1]],
]);

it('rejects an ipn without a signature', function () {
    new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(nowpaymentsSignedRequest(nowpaymentsIpn(), sign: false));
})->throws(PaymentException::class);

it('rejects an ipn signed with the wrong secret', function () {
    new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(nowpaymentsSignedRequest(nowpaymentsIpn(), secret: 'np-ipn-other'));
})->throws(PaymentException::class);

it('verifies signatures over recursively sorted nested fields', function () {
    $result = new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(nowpaymentsSignedRequest(nowpaymentsIpn([
            'outcome' => ['z' => 'last', 'a' => 'first'],
        ])));

    expect($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('rejects malformed ipn bodies', function (string $body) {
    new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(Request::create('/api/v1/payment/webhook/nowpayments', 'POST', [], [], [], [], $body));
})->throws(PaymentException::class)->with([
    'not json' => ['not-json'],
    'not an object' => ['"scalar"'],
]);

it('rejects an ipn with an empty order reference', function () {
    new NowpaymentsPaymentDriver(app(NowpaymentsClient::class))
        ->handleWebhook(nowpaymentsSignedRequest(nowpaymentsIpn(['order_id' => ''])));
})->throws(PaymentException::class);
