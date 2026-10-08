<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\Drivers\AdyenPaymentDriver;
use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;
use Modules\Payment\Gateways\Drivers\NowpaymentsPaymentDriver;
use Modules\Payment\Gateways\Drivers\PaypalPaymentDriver;
use Modules\Payment\Gateways\Drivers\StripePaymentDriver;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    config([
        'payment.stripe.secret' => null,
        'payment.paypal.client_id' => null,
        'payment.paypal.client_secret' => null,
        'payment.adyen.api_key' => null,
        'payment.adyen.merchant_account' => null,
        'payment.nowpayments.api_key' => null,
        'payment.nowpayments.ipn_secret' => null,
    ]);
});

it('resolves every registered driver by method and by name', function (PaymentMethodEnum $method, string $driver) {
    $manager = app(PaymentDriverManager::class);

    expect($manager->forMethod($method))->toBeInstanceOf($driver)
        ->and($manager->forName($method->value))->toBeInstanceOf($driver);
})->with([
    'manual' => [PaymentMethodEnum::MANUAL, ManualPaymentDriver::class],
    'stripe' => [PaymentMethodEnum::STRIPE, StripePaymentDriver::class],
    'paypal' => [PaymentMethodEnum::PAYPAL, PaypalPaymentDriver::class],
    'adyen' => [PaymentMethodEnum::ADYEN, AdyenPaymentDriver::class],
    'nowpayments' => [PaymentMethodEnum::NOWPAYMENTS, NowpaymentsPaymentDriver::class],
]);

it('throws for an unknown driver name', function () {
    expect(fn () => app(PaymentDriverManager::class)->forName('crypto'))
        ->toThrow(PaymentException::class);
});

it('reports a driver configured only when its credentials are set', function () {
    $manager = app(PaymentDriverManager::class);

    expect($manager->isConfigured(PaymentMethodEnum::MANUAL))->toBeTrue()
        ->and($manager->isConfigured(PaymentMethodEnum::STRIPE))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::PAYPAL))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::ADYEN))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::NOWPAYMENTS))->toBeFalse();

    config(['payment.stripe.secret' => 'sk_test_x']);
    config(['payment.paypal.client_id' => 'cid_test']);
    config(['payment.adyen.api_key' => 'AQE1hmfxKIPuJvh5BA']);
    config(['payment.nowpayments.api_key' => 'NP-API-KEY']);

    expect($manager->isConfigured(PaymentMethodEnum::STRIPE))->toBeTrue()
        // paypal, adyen and nowpayments need both halves of their credential pairs
        ->and($manager->isConfigured(PaymentMethodEnum::PAYPAL))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::ADYEN))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::NOWPAYMENTS))->toBeFalse();

    config(['payment.paypal.client_secret' => 'secret_test']);
    config(['payment.adyen.merchant_account' => 'BaxelaECOM']);
    config(['payment.nowpayments.ipn_secret' => 'np-ipn-test']);

    expect($manager->isConfigured(PaymentMethodEnum::PAYPAL))->toBeTrue()
        ->and($manager->isConfigured(PaymentMethodEnum::ADYEN))->toBeTrue()
        ->and($manager->isConfigured(PaymentMethodEnum::NOWPAYMENTS))->toBeTrue();
});
