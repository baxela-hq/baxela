<?php

use Illuminate\Http\Request;
use Mockery\MockInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\AdyenCheckout;
use Modules\Payment\Gateways\Drivers\AdyenPaymentDriver;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(HelperTrait::class);

beforeEach(function () {
    config([
        'payment.adyen.api_key' => 'AQE1hmfxKIPuJvh5BA',
        'payment.adyen.merchant_account' => 'BaxelaECOM',
        // The config holds the Customer Area key base64-encoded, exactly as
        // Adyen issues it; the wrapper decodes it before verifying.
        'payment.adyen.hmac_key' => base64_encode('adyen-hmac-test'),
        'payment.adyen.return_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
    ]);
});

/**
 * Driver wired to a mocked AdyenCheckout; $configureClient sets the API
 * expectations (and can capture the request params).
 */
function adyenDriver(callable $configureClient): AdyenPaymentDriver
{
    $checkout = Mockery::mock(AdyenCheckout::class);
    $configureClient($checkout);

    return new AdyenPaymentDriver($checkout);
}

/**
 * Default AUTHORISATION NotificationRequestItem for payment 9; no
 * originalReference — first events on a payment never carry one.
 */
function adyenItem(array $overrides = []): array
{
    return array_merge([
        'pspReference' => '8515131751004933',
        'merchantAccountCode' => 'BaxelaECOM',
        'merchantReference' => '9',
        'eventCode' => 'AUTHORISATION',
        'success' => true,
    ], $overrides);
}

/**
 * Real signed standard-webhook delivery — exercises the actual HMAC
 * verification path. $signItem (defaults to $item) lets tests tamper with
 * the delivered item while signing the original; $sign = false omits the
 * signature entirely.
 */
function adyenWebhook(array $item, ?array $signItem = null, bool $sign = true, string $rawKey = 'adyen-hmac-test'): Request
{
    if ($sign) {
        $item['additionalData']['hmacSignature'] = adyenSignature($signItem ?? $item, $rawKey);
    }

    $body = json_encode(['live' => 'false', 'notificationItems' => [['NotificationRequestItem' => $item]]], JSON_THROW_ON_ERROR);

    return Request::create('/api/v1/payment/webhook/adyen', 'POST', [], [], [], [], $body);
}

/**
 * Adyen's documented signing recipe, mirrored here so the driver is
 * verified against independently computed signatures.
 */
function adyenSignature(array $item, string $rawKey): string
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

    return base64_encode(hash_hmac('sha256', $canonical, $rawKey, true));
}

it('creates a payment link with minor units, reference and return url', function () {
    $captured = [];
    $driver = adyenDriver(function (MockInterface $checkout) use (&$captured): void {
        $checkout->shouldReceive('createPaymentLink')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn((object) [
                'id' => 'PLFF741A32D9E3F5B',
                'url' => 'https://checkout-test.adyen.com/link/PLFF741A32D9E3F5B',
                'status' => 'active',
            ]);
    });

    $result = $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'adyen',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));

    expect($result->redirect_url)->toBe('https://checkout-test.adyen.com/link/PLFF741A32D9E3F5B')
        ->and($result->transaction_id)->toBe('9')
        ->and($captured['reference'])->toBe('9')
        ->and($captured['merchantAccount'])->toBe('BaxelaECOM')
        ->and($captured['amount'])->toBe(['currency' => 'USD', 'value' => 30500])
        ->and($captured['returnUrl'])->toBe('https://shop.test/en/payment/return?order_code=ABCD2345');
});

it('respects zero-decimal currencies', function () {
    $captured = [];
    $driver = adyenDriver(function (MockInterface $checkout) use (&$captured): void {
        $checkout->shouldReceive('createPaymentLink')
            ->once()
            ->with(Mockery::on(function (array $params) use (&$captured): bool {
                $captured = $params;

                return true;
            }))
            ->andReturn((object) [
                'id' => 'PLFF741A32D9E3F5B',
                'url' => 'https://checkout-test.adyen.com/link/PLFF741A32D9E3F5B',
                'status' => 'active',
            ]);
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'adyen',
        order_code: 'ABCD2345',
        currency: 'JPY',
        currency_decimal_places: 0,
    ));

    expect($captured['amount'])->toBe(['currency' => 'JPY', 'value' => 305]);
});

it('refuses to initiate when the gateway is not configured', function (array $config) {
    config($config);
    $driver = new AdyenPaymentDriver(app(AdyenCheckout::class));

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'adyen',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));
})->throws(PaymentException::class)->with([
    'missing api key' => [['payment.adyen.api_key' => null]],
    'missing merchant account' => [['payment.adyen.merchant_account' => null]],
]);

it('fails loudly when the link response is missing its url', function () {
    $driver = adyenDriver(function (MockInterface $checkout): void {
        $checkout->shouldReceive('createPaymentLink')
            ->andReturn((object) ['id' => 'PLFF741A32D9E3F5B', 'status' => 'active']);
    });

    $driver->initiate(new PaymentInitiateInput(
        payment_id: 9,
        order_id: 4,
        amount: 305.0,
        method: 'adyen',
        order_code: 'ABCD2345',
        currency: 'USD',
        currency_decimal_places: 2,
    ));
})->throws(RuntimeException::class);

it('refuses webhooks when the hmac key is not configured', function () {
    config(['payment.adyen.hmac_key' => null]);

    new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(adyenWebhook(adyenItem()));
})->throws(PaymentException::class);

it('settles an authorised notification', function () {
    $result = new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(adyenWebhook(adyenItem()));

    expect($result->transaction_id)->toBe('9')
        ->and($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('marks a refused notification failed', function () {
    $result = new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(adyenWebhook(adyenItem(['success' => false])));

    expect($result->status)->toBe(PaymentStatusEnum::FAILED->value);
});

it('rejects notifications whose signed fields were tampered with', function (array $tampered) {
    new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(adyenWebhook(adyenItem($tampered), signItem: adyenItem()));
})->throws(PaymentException::class)->with([
    'psp reference' => [['pspReference' => '8836123456789012']],
    'merchant reference' => [['merchantReference' => '10']],
    'merchant account' => [['merchantAccountCode' => 'OtherECOM']],
    'success' => [['success' => false]],
]);

it('rejects a notification without an hmac signature', function () {
    new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(adyenWebhook(adyenItem(), sign: false));
})->throws(PaymentException::class);

it('rejects a notification signed with the wrong key', function () {
    new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(adyenWebhook(adyenItem(), rawKey: 'adyen-hmac-other'));
})->throws(PaymentException::class);

it('accepts references containing separator characters', function () {
    $result = new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(adyenWebhook(adyenItem(['merchantReference' => 'ORD:9\\X'])));

    expect($result->transaction_id)->toBe('ORD:9\\X')
        ->and($result->status)->toBe(PaymentStatusEnum::SUCCESS->value);
});

it('rejects malformed notification structures', function (string $body) {
    new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(Request::create('/api/v1/payment/webhook/adyen', 'POST', [], [], [], [], $body));
})->throws(PaymentException::class)->with([
    'no notification items' => ['{"live":"false"}'],
    'empty items' => ['{"notificationItems":[]}'],
    'batched items' => ['{"notificationItems":[{"NotificationRequestItem":'.json_encode(adyenItem()).'},{"NotificationRequestItem":'.json_encode(adyenItem()).'}]}'],
    'item without the wrapper key' => ['{"notificationItems":[{"NotificationRequest":'.json_encode(adyenItem()).'}]}'],
]);

it('rejects a notification with an empty merchant reference', function () {
    new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(adyenWebhook(adyenItem(['merchantReference' => ''])));
})->throws(PaymentException::class);

it('rejects event codes it cannot settle', function () {
    new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(adyenWebhook(adyenItem(['eventCode' => 'CANCELLATION'])));
})->throws(PaymentException::class);

it('rejects a webhook whose body is not valid json', function () {
    new AdyenPaymentDriver(app(AdyenCheckout::class))
        ->handleWebhook(Request::create('/api/v1/payment/webhook/adyen', 'POST', [], [], [], [], 'not-json'));
})->throws(PaymentException::class);
