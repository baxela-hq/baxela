<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('denies a guest with 401', function () {
    $this->getJson($this->baseUrl('/admin/methods'))->assertStatus(401);
});

it('lazily creates disabled rows for every registered driver', function () {
    config([
        'payment.stripe.secret' => 'sk_test_x',
        'payment.stripe.webhook_secret' => 'whsec_test_x',
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
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/methods'))->assertOk();

    $rows = collect($response->json('data'));

    expect($rows->pluck('method')->all())->toEqualCanonicalizing(['manual', 'stripe', 'paypal', 'adyen', 'nowpayments', 'mercadopago', 'checkoutcom', 'razorpay'])
        ->and(PaymentMethod::count())->toBe(8);

    $stripe = $rows->firstWhere('method', 'stripe');
    expect($stripe[PaymentMethodSchema::IS_ACTIVE])->toBeFalse()
        ->and($stripe[PaymentMethodSchema::IS_CONFIGURED])->toBeTrue()
        ->and($stripe[PaymentMethodSchema::IS_REGISTERED])->toBeTrue();

    $manual = $rows->firstWhere('method', 'manual');
    expect($manual[PaymentMethodSchema::IS_CONFIGURED])->toBeTrue();

    // Registered but missing its credential pair: offered to no one until
    // the env credentials exist.
    $paypal = $rows->firstWhere('method', 'paypal');
    expect($paypal[PaymentMethodSchema::IS_ACTIVE])->toBeFalse()
        ->and($paypal[PaymentMethodSchema::IS_CONFIGURED])->toBeFalse()
        ->and($paypal[PaymentMethodSchema::IS_REGISTERED])->toBeTrue();
});

it('does not create rows for drivers without an implementation', function () {
    // paypal is registered by default; drop it from the registry to cover
    // the enum-case-without-driver gap.
    config(['payment.drivers' => collect(config('payment.drivers'))->except('paypal')->all()]);
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())
        ->not->toContain('paypal')
        ->and(PaymentMethod::count())->toBe(7);
});

it('lazily creates an inactive row for a driver an extension registers', function () {
    // How an extension-provided driver appears: config registration only,
    // no core change — the admin list picks it up as disabled.
    $driver = new class implements ConfigurableDriver, PaymentDriverInterface
    {
        public function credentialKeys(): array
        {
            return ['api_key'];
        }

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
    config([
        'payment.drivers.sandbox' => $driver::class,
        'payment.sandbox.api_key' => null,
    ]);
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/methods'))->assertOk();

    $sandbox = collect($response->json('data'))->firstWhere('method', 'sandbox');

    expect($sandbox)->not->toBeNull()
        ->and($sandbox[PaymentMethodSchema::IS_ACTIVE])->toBeFalse()
        ->and($sandbox[PaymentMethodSchema::IS_REGISTERED])->toBeTrue()
        ->and($sandbox[PaymentMethodSchema::IS_CONFIGURED])->toBeFalse();
});

it('flags a gateway whose credentials are missing', function () {
    config(['payment.stripe.secret' => null]);
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/methods'))->assertOk();

    $stripe = collect($response->json('data'))->firstWhere('method', 'stripe');

    expect($stripe[PaymentMethodSchema::IS_CONFIGURED])->toBeFalse()
        ->and($stripe[PaymentMethodSchema::IS_REGISTERED])->toBeTrue();
});

it('exposes per-driver refund capability', function () {
    $this->actingAs($this->superAdminUser());

    $rows = collect($this->getJson($this->baseUrl('/admin/methods'))->assertOk()->json('data'));

    $refundable = $rows->where(PaymentMethodSchema::IS_REFUNDABLE, true)->pluck('method')->all();
    $notRefundable = $rows->where(PaymentMethodSchema::IS_REFUNDABLE, false)->pluck('method')->all();

    expect($refundable)->toEqualCanonicalizing(['manual', 'stripe', 'paypal', 'adyen', 'checkoutcom', 'mercadopago', 'razorpay'])
        ->and($notRefundable)->toEqualCanonicalizing(['nowpayments']);
});

it('activates a method and moves its checkout position', function () {
    $this->actingAs($this->superAdminUser());
    $this->getJson($this->baseUrl('/admin/methods'))->assertOk();

    $method = PaymentMethod::query()
        ->where(PaymentMethodSchema::METHOD, 'stripe')
        ->first();

    $this->patchJson($this->baseUrl('/admin/methods/'.$method->id), [
        'is_active' => true,
        'sort_order' => 15,
    ])->assertOk()
        ->assertJsonPath('data.'.PaymentMethodSchema::IS_ACTIVE, true)
        ->assertJsonPath('data.'.PaymentMethodSchema::SORT_ORDER, 15);

    expect($method->refresh()->{PaymentMethodSchema::IS_ACTIVE})->toBeTrue()
        ->and($method->{PaymentMethodSchema::SORT_ORDER})->toBe(15);
});

it('refuses updating a method whose driver is no longer registered', function () {
    config(['payment.drivers' => collect(config('payment.drivers'))->except('paypal')->all()]);
    $this->actingAs($this->superAdminUser());
    $method = PaymentMethod::query()->create([
        PaymentMethodSchema::METHOD => 'paypal',
        PaymentMethodSchema::IS_ACTIVE => false,
        PaymentMethodSchema::SORT_ORDER => 30,
    ]);

    $this->patchJson($this->baseUrl('/admin/methods/'.$method->id), [
        'is_active' => true,
        'sort_order' => 30,
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.method_not_supported');
});

it('rejects an unknown method id', function () {
    $this->actingAs($this->superAdminUser());

    $this->patchJson($this->baseUrl('/admin/methods/999'), [
        'is_active' => true,
        'sort_order' => 10,
    ])->assertStatus(404);
});

it('validates the payload shape', function () {
    $this->actingAs($this->superAdminUser());

    $this->patchJson($this->baseUrl('/admin/methods/1'), [
        'is_active' => true,
    ])->assertStatus(422);
});
