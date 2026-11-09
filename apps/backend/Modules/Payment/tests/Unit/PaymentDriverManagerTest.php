<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
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
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    config([
        'payment.stripe.secret' => null,
        'payment.stripe.webhook_secret' => null,
        'payment.paypal.client_id' => null,
        'payment.paypal.client_secret' => null,
        'payment.paypal.webhook_id' => null,
        'payment.adyen.api_key' => null,
        'payment.adyen.merchant_account' => null,
        'payment.adyen.hmac_key' => null,
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

it('resolves every registered driver by name', function (string $method, string $driver) {
    expect(app(PaymentDriverManager::class)->forName($method))->toBeInstanceOf($driver);
})->with([
    'manual' => ['manual', ManualPaymentDriver::class],
    'stripe' => ['stripe', StripePaymentDriver::class],
    'paypal' => ['paypal', PaypalPaymentDriver::class],
    'adyen' => ['adyen', AdyenPaymentDriver::class],
    'nowpayments' => ['nowpayments', NowpaymentsPaymentDriver::class],
    'mercadopago' => ['mercadopago', MercadopagoPaymentDriver::class],
    'checkoutcom' => ['checkoutcom', CheckoutcomPaymentDriver::class],
    'razorpay' => ['razorpay', RazorpayPaymentDriver::class],
]);

it('throws for an unknown driver name', function () {
    expect(fn () => app(PaymentDriverManager::class)->forName('crypto'))
        ->toThrow(PaymentException::class);
});

it('reports an unregistered driver as inert, without throwing', function () {
    $manager = app(PaymentDriverManager::class);

    expect($manager->isRegistered('crypto'))->toBeFalse()
        ->and($manager->isConfigured('crypto'))->toBeFalse()
        ->and($manager->supportsRefund('crypto'))->toBeFalse();
});

it('fails closed for a driver that declares no ConfigurableDriver contract', function () {
    $driver = new class implements PaymentDriverInterface
    {
        // Deliberately not ConfigurableDriver: the platform cannot know
        // what an undeclaring driver needs, so it must count as
        // unconfigured rather than be offered half-operational.
        public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
        {
            throw new RuntimeException('never called');
        }

        public function handleWebhook(Request $request): WebhookResult
        {
            throw new RuntimeException('never called');
        }

        public function refund(PaymentRefundInput $input): PaymentRefundResult
        {
            throw new RuntimeException('never called');
        }
    };
    config(['payment.drivers.opaque' => $driver::class]);

    $manager = app(PaymentDriverManager::class);

    expect($manager->isRegistered('opaque'))->toBeTrue()
        ->and($manager->isConfigured('opaque'))->toBeFalse();
});

it('reports a driver configured only when its full lifecycle credentials are set', function () {
    $manager = app(PaymentDriverManager::class);

    expect($manager->isConfigured('manual'))->toBeTrue()
        ->and($manager->isConfigured('stripe'))->toBeFalse()
        ->and($manager->isConfigured('paypal'))->toBeFalse()
        ->and($manager->isConfigured('adyen'))->toBeFalse()
        ->and($manager->isConfigured('nowpayments'))->toBeFalse()
        ->and($manager->isConfigured('mercadopago'))->toBeFalse()
        ->and($manager->isConfigured('checkoutcom'))->toBeFalse()
        ->and($manager->isConfigured('razorpay'))->toBeFalse();

    // The initiate halves alone configure nothing: every driver also
    // needs the secret its webhook verification keys on, or payments it
    // starts can never settle.
    config(['payment.stripe.secret' => 'sk_test_x']);
    config(['payment.paypal.client_id' => 'cid_test']);
    config(['payment.paypal.client_secret' => 'secret_test']);
    config(['payment.adyen.api_key' => 'ADYEN_TEST_KEY_PLACEHOLDER']);
    config(['payment.adyen.merchant_account' => 'BaxelaECOM']);
    config(['payment.nowpayments.api_key' => 'NP-API-KEY']);
    config(['payment.mercadopago.access_token' => 'MP-ACCESS-TOKEN']);
    config(['payment.checkoutcom.secret_key' => 'sk_test_cko']);
    config(['payment.razorpay.key_id' => 'rzp_test_x']);
    config(['payment.razorpay.key_secret' => 'rzp_secret_test']);

    expect($manager->isConfigured('stripe'))->toBeFalse()
        ->and($manager->isConfigured('paypal'))->toBeFalse()
        ->and($manager->isConfigured('adyen'))->toBeFalse()
        ->and($manager->isConfigured('nowpayments'))->toBeFalse()
        ->and($manager->isConfigured('mercadopago'))->toBeFalse()
        ->and($manager->isConfigured('checkoutcom'))->toBeFalse()
        ->and($manager->isConfigured('razorpay'))->toBeFalse();

    // The verification halves flip each driver to configured.
    config(['payment.stripe.webhook_secret' => 'whsec_test_x']);
    config(['payment.paypal.webhook_id' => 'WH-1234']);
    config(['payment.adyen.hmac_key' => 'hmac_test_base64']);
    config(['payment.nowpayments.ipn_secret' => 'np-ipn-test']);
    config(['payment.mercadopago.webhook_secret' => 'mp-webhook-test']);
    config(['payment.checkoutcom.webhook_secret' => 'cko-webhook-test']);
    config(['payment.razorpay.webhook_secret' => 'rzp-webhook-test']);

    expect($manager->isConfigured('stripe'))->toBeTrue()
        ->and($manager->isConfigured('paypal'))->toBeTrue()
        ->and($manager->isConfigured('adyen'))->toBeTrue()
        ->and($manager->isConfigured('nowpayments'))->toBeTrue()
        ->and($manager->isConfigured('mercadopago'))->toBeTrue()
        ->and($manager->isConfigured('checkoutcom'))->toBeTrue()
        ->and($manager->isConfigured('razorpay'))->toBeTrue();
});

it('reports refund support only for drivers implementing RefundableDriver', function () {
    $manager = app(PaymentDriverManager::class);

    expect($manager->supportsRefund('manual'))->toBeTrue()
        ->and($manager->supportsRefund('stripe'))->toBeTrue()
        ->and($manager->supportsRefund('paypal'))->toBeTrue()
        // Every gateway except NowPayments has a refund driver now
        // (gap-B4); NowPayments is provider-impossible (crypto).
        ->and($manager->supportsRefund('adyen'))->toBeTrue()
        ->and($manager->supportsRefund('checkoutcom'))->toBeTrue()
        ->and($manager->supportsRefund('mercadopago'))->toBeTrue()
        ->and($manager->supportsRefund('razorpay'))->toBeTrue()
        ->and($manager->supportsRefund('nowpayments'))->toBeFalse();

    // An unregistered driver is never refundable, whatever its class.
    config(['payment.drivers' => collect(config('payment.drivers'))->except('stripe')->all()]);

    expect($manager->supportsRefund('stripe'))->toBeFalse();
});
