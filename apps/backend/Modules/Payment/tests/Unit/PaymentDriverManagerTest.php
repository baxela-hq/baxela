<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\Drivers\AdyenPaymentDriver;
use Modules\Payment\Gateways\Drivers\CheckoutcomPaymentDriver;
use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;
use Modules\Payment\Gateways\Drivers\MercadopagoPaymentDriver;
use Modules\Payment\Gateways\Drivers\NowpaymentsPaymentDriver;
use Modules\Payment\Gateways\Drivers\PaypalPaymentDriver;
use Modules\Payment\Gateways\Drivers\RazorpayPaymentDriver;
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
        'payment.mercadopago.access_token' => null,
        'payment.mercadopago.webhook_secret' => null,
        'payment.checkoutcom.secret_key' => null,
        'payment.checkoutcom.webhook_secret' => null,
        'payment.razorpay.key_id' => null,
        'payment.razorpay.key_secret' => null,
        'payment.razorpay.webhook_secret' => null,
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
    'mercadopago' => [PaymentMethodEnum::MERCADOPAGO, MercadopagoPaymentDriver::class],
    'checkoutcom' => [PaymentMethodEnum::CHECKOUTCOM, CheckoutcomPaymentDriver::class],
    'razorpay' => [PaymentMethodEnum::RAZORPAY, RazorpayPaymentDriver::class],
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
        ->and($manager->isConfigured(PaymentMethodEnum::NOWPAYMENTS))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::MERCADOPAGO))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::CHECKOUTCOM))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::RAZORPAY))->toBeFalse();

    config(['payment.stripe.secret' => 'sk_test_x']);
    config(['payment.paypal.client_id' => 'cid_test']);
    config(['payment.adyen.api_key' => 'AQE1hmfxKIPuJvh5BA']);
    config(['payment.nowpayments.api_key' => 'NP-API-KEY']);
    config(['payment.mercadopago.access_token' => 'MP-ACCESS-TOKEN']);
    config(['payment.checkoutcom.secret_key' => 'sk_test_cko']);
    config(['payment.razorpay.key_id' => 'rzp_test_x']);

    expect($manager->isConfigured(PaymentMethodEnum::STRIPE))->toBeTrue()
        // the paired-credential drivers need both halves
        ->and($manager->isConfigured(PaymentMethodEnum::PAYPAL))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::ADYEN))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::NOWPAYMENTS))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::MERCADOPAGO))->toBeFalse()
        ->and($manager->isConfigured(PaymentMethodEnum::CHECKOUTCOM))->toBeFalse()
        // razorpay needs the key pair AND the webhook secret
        ->and($manager->isConfigured(PaymentMethodEnum::RAZORPAY))->toBeFalse();

    config(['payment.paypal.client_secret' => 'secret_test']);
    config(['payment.adyen.merchant_account' => 'BaxelaECOM']);
    config(['payment.nowpayments.ipn_secret' => 'np-ipn-test']);
    config(['payment.mercadopago.webhook_secret' => 'mp-webhook-test']);
    config(['payment.checkoutcom.webhook_secret' => 'cko-webhook-test']);
    config(['payment.razorpay.key_secret' => 'rzp_secret_test']);

    expect($manager->isConfigured(PaymentMethodEnum::PAYPAL))->toBeTrue()
        ->and($manager->isConfigured(PaymentMethodEnum::ADYEN))->toBeTrue()
        ->and($manager->isConfigured(PaymentMethodEnum::NOWPAYMENTS))->toBeTrue()
        ->and($manager->isConfigured(PaymentMethodEnum::MERCADOPAGO))->toBeTrue()
        ->and($manager->isConfigured(PaymentMethodEnum::CHECKOUTCOM))->toBeTrue()
        // still missing the webhook secret
        ->and($manager->isConfigured(PaymentMethodEnum::RAZORPAY))->toBeFalse();

    config(['payment.razorpay.webhook_secret' => 'rzp-webhook-test']);

    expect($manager->isConfigured(PaymentMethodEnum::RAZORPAY))->toBeTrue();
});
