<?php

use Illuminate\Http\Request;
use Mockery\MockInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\Drivers\RazorpayPaymentDriver;
use Modules\Payment\Gateways\RazorpayClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(HelperTrait::class);

beforeEach(function () {
    config([
        'payment.razorpay.key_id' => 'rzp_test_x',
        'payment.razorpay.key_secret' => 'rzp_secret_test',
        'payment.razorpay.webhook_secret' => 'rzp-webhook-test',
        'payment.razorpay.return_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
    ]);
});

/**
 * Driver wired to a mocked RazorpayClient; $configureClient sets the API
 * expectations (and can capture the request params).
 */
function razorpayDriver(callable $configureClient): RazorpayPaymentDriver
{
    $client = Mockery::mock(RazorpayClient::class);
    $configureClient($client);

    return new RazorpayPaymentDriver($client);
}

/**
 * Payment-link response: the driver only reads the short URL.
 */
function razorpayLinkResponse(): object
{
    return (object) [
        'id' => 'plink_KfskYmqDPG9hXY',
        'short_url' => 'https://rzp.io/i/abc123',
        'status' => 'created',
        'amount' => 30500,
        'currency' => 'INR',
    ];
}

/**
 * Default paid webhook for payment 9.
 */
function razorpayEventPayload(array $overrides = []): array
{
    return array_merge([
        'event' => 'payment_link.paid',
        'payload' => [
            'payment_link' => [
                'entity' => [
                    'id' => 'plink_KfskYmqDPG9hXY',
                    'reference_id' => '9',
                    'status' => 'paid',
                    'amount' => 30500,
                    'currency' => 'INR',
                    'short_url' => 'https://rzp.io/i/abc123',
                ],
            ],
            'payment' => [
                'entity' => [
                    'id' => 'pay_NUuTZZFtuv7zxa',
                    'status' => 'captured',
                    'amount' => 30500,
                ],
            ],
        ],
    ], $overrides);
}

/**
 * Real signed webhook delivery — exercises the actual raw-body HMAC
 * verification path. $rawBodyOverride delivers different bytes than the
 * ones signed, simulating post-signing tampering; $sign = false omits the
 * header entirely.
 */
function razorpayEvent(array $payload, ?string $rawBodyOverride = null, bool $sign = true, string $secret = 'rzp-webhook-test'): Request
{
    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
    $headers = [];

    if ($sign) {
        $headers['HTTP_X_RAZORPAY_SIGNATURE'] = razorpaySignature($rawBodyOverride ?? $rawBody, $secret);
    }

    return Request::create('/api/v1/payment/webhook/razorpay', 'POST', [], [], [], $headers, $rawBody);
}

/**
 * Razorpay's documented signing recipe, mirrored here so the driver is
 * verified against independently computed signatures: hex HMAC-SHA256
 * over the exact raw body bytes.
 */
function razorpaySignature(string $rawBody, string $secret): string
{
    return hash_hmac('sha256', $rawBody, $secret);
}

it('creates a payment link with minor units, reference and callback url', function () {
    $captured = [];
    $driver = razorpayDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createPaymentLink')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn(razorpayLinkResponse());
    });

    $result = $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'razorpay',
        order_code: 'ABCD2345',
        currency: 'INR',
        currency_decimal_places: 2,
    ));

    expect($result->redirect_url)->toBe('https://rzp.io/i/abc123')
        ->and($result->transaction_id)->toBe('9')
        ->and($captured['reference_id'])->toBe('9')
        ->and($captured['amount'])->toBe(30500)
        ->and($captured['currency'])->toBe('INR')
        ->and($captured['callback_url'])->toBe('https://shop.test/en/payment/return?order_code=ABCD2345');
});

it('refuses to initiate when the gateway is not configured', function (array $config) {
    config($config);
    $driver = new RazorpayPaymentDriver(app(RazorpayClient::class));

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'razorpay',
        order_code: 'ABCD2345',
        currency: 'INR',
        currency_decimal_places: 2,
    ));
})->throws(PaymentException::class)->with([
    'missing key id' => [['payment.razorpay.key_id' => null]],
    'missing key secret' => [['payment.razorpay.key_secret' => null]],
]);

it('fails loudly when the link response is missing its short url', function () {
    $driver = razorpayDriver(function (MockInterface $client): void {
        $client->shouldReceive('createPaymentLink')
            ->andReturn((object) ['id' => 'plink_KfskYmqDPG9hXY', 'status' => 'created']);
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'razorpay',
        order_code: 'ABCD2345',
        currency: 'INR',
        currency_decimal_places: 2,
    ));
})->throws(RuntimeException::class);

it('refuses webhooks when the webhook secret is not configured', function () {
    config(['payment.razorpay.webhook_secret' => null]);

    new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent(razorpayEventPayload()));
})->throws(PaymentException::class);

it('settles a paid link event', function () {
    $result = new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent(razorpayEventPayload()));

    expect($result->transaction_id)->toBe('9')
        ->and($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('maps a cancelled link to failed', function () {
    $result = new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent(razorpayEventPayload(['event' => 'payment_link.cancelled'])));

    expect($result->status)->toBe(PaymentStatusEnum::FAILED->value);
});

it('rejects non-terminal events so the payment stays pending', function (string $event) {
    new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent(razorpayEventPayload(['event' => $event])));
})->throws(PaymentException::class)->with([
    'failed attempt is not terminal' => ['payment.failed'],
    'captured payment alone is not the link event' => ['payment.captured'],
    'link created is informational' => ['payment_link.created'],
]);

it('rejects a webhook whose body was modified after signing', function () {
    $tampered = json_encode(razorpayEventPayload([
        'payload' => ['payment_link' => ['entity' => ['id' => 'plink_KfskYmqDPG9hXY', 'reference_id' => '10', 'status' => 'paid', 'amount' => 1, 'currency' => 'INR', 'short_url' => 'https://rzp.io/i/other']]],
    ]), JSON_THROW_ON_ERROR);

    new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent(razorpayEventPayload(), rawBodyOverride: $tampered));
})->throws(PaymentException::class);

it('rejects a webhook signed with the wrong secret', function () {
    new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent(razorpayEventPayload(), secret: 'rzp-webhook-other'));
})->throws(PaymentException::class);

it('rejects a webhook without a signature', function () {
    new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent(razorpayEventPayload(), sign: false));
})->throws(PaymentException::class);

it('rejects an event with an empty reference', function () {
    new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent(razorpayEventPayload([
            'payload' => ['payment_link' => ['entity' => ['id' => 'plink_KfskYmqDPG9hXY', 'reference_id' => '', 'status' => 'paid', 'amount' => 30500, 'currency' => 'INR', 'short_url' => 'https://rzp.io/i/abc123']]],
        ])));
})->throws(PaymentException::class);

it('rejects a webhook whose body is not valid json even when correctly signed', function () {
    $rawBody = 'not-json';

    new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(Request::create('/api/v1/payment/webhook/razorpay', 'POST', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => razorpaySignature($rawBody, 'rzp-webhook-test'),
        ], $rawBody));
})->throws(PaymentException::class);

function razorpayRefundInput(array $overrides = []): PaymentRefundInput
{
    $base = [
        'payment_id' => 1,
        'order_id' => 2,
        'transaction_id' => '1',
        'amount' => 10.0,
        'currency' => 'INR',
        'currency_decimal_places' => 2,
        'idempotency_key' => 'refund-uuid-1',
        'gateway_transaction_id' => 'pay_NUuTZZFtuv7zxa',
    ];

    return new PaymentRefundInput(...array_values(array_merge($base, $overrides)));
}

it('refunds the captured payment with paise, notes and the refund idempotency key', function () {
    $captured = [];
    $driver = razorpayDriver(function (MockInterface $client) use (&$captured): void {
        $client->shouldReceive('createRefund')
            ->once()
            ->with(
                'pay_NUuTZZFtuv7zxa',
                Mockery::on(function (array $params) use (&$captured): bool {
                    $captured = $params;

                    return true;
                }),
                'refund-uuid-1',
            )
            ->andReturn((object) ['id' => 'rfnd_1', 'status' => 'pending']);
    });

    $result = $driver->refund(razorpayRefundInput());

    // Normal refunds go pending → processed — never completed money
    // movement until refund.processed confirms.
    expect($result->transaction_id)->toBe('rfnd_1')
        ->and($result->accepted_pending)->toBeTrue()
        ->and($captured['amount'])->toBe(1000)
        ->and($captured['notes'])->toBe(['baxela_refund_uuid' => 'refund-uuid-1']);
});

it('treats an already processed refund response as definitive', function () {
    $driver = razorpayDriver(function (MockInterface $client): void {
        $client->shouldReceive('createRefund')
            ->once()
            ->andReturn((object) ['id' => 'rfnd_2', 'status' => 'processed']);
    });

    $result = $driver->refund(razorpayRefundInput());

    expect($result->transaction_id)->toBe('rfnd_2')
        ->and($result->accepted_pending)->toBeFalse();
});

it('refuses to refund a payment without a persisted pay id', function () {
    $driver = razorpayDriver(function (MockInterface $client): void {
        $client->shouldNotReceive('createRefund');
    });

    $driver->refund(razorpayRefundInput(['gateway_transaction_id' => null]));
})->throws(PaymentException::class, 'payment.refund.not_supported');

it('routes a refund processed webhook to the refund ledger via the echoed notes', function () {
    $result = new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent([
            'event' => 'refund.processed',
            'payload' => [
                'refund' => [
                    'entity' => [
                        'id' => 'rfnd_1',
                        'payment_id' => 'pay_NUuTZZFtuv7zxa',
                        'amount' => 1000,
                        'status' => 'processed',
                        'notes' => ['baxela_refund_uuid' => 'refund-uuid-1'],
                    ],
                ],
            ],
        ]));

    expect($result->refund_outcome)->not->toBeNull()
        ->and($result->refund_outcome->reference)->toBe('refund-uuid-1')
        ->and($result->refund_outcome->gateway_refund_id)->toBe('rfnd_1')
        ->and($result->refund_outcome->gateway_payment_id)->toBe('pay_NUuTZZFtuv7zxa')
        ->and($result->refund_outcome->status)->toBe('success')
        ->and($result->refund_outcome->amount_minor)->toBe(1000);
});

it('routes a refund failed webhook as a definitive failure', function () {
    $result = new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent([
            'event' => 'refund.failed',
            'payload' => [
                'refund' => [
                    'entity' => [
                        'id' => 'rfnd_2',
                        'payment_id' => 'pay_NUuTZZFtuv7zxa',
                        'amount' => 1000,
                        'notes' => ['baxela_refund_uuid' => 'refund-uuid-1'],
                    ],
                ],
            ],
        ]));

    expect($result->refund_outcome->status)->toBe('failed');
});

it('rejects a refund webhook without any correlation key', function () {
    new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent([
            'event' => 'refund.processed',
            'payload' => [
                'refund' => [
                    'entity' => ['amount' => 1000],
                ],
            ],
        ]));
})->throws(PaymentException::class);

it('still persists the captured pay id from a paid link webhook', function () {
    $result = new RazorpayPaymentDriver(app(RazorpayClient::class))
        ->handleWebhook(razorpayEvent(razorpayEventPayload()));

    expect($result->refund_outcome)->toBeNull()
        ->and($result->gateway_transaction_id)->toBe('pay_NUuTZZFtuv7zxa');
});
