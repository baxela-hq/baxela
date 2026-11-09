<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function activeListedMethod(string $method, int $sortOrder = 0): PaymentMethod
{
    return PaymentMethod::query()->create([
        PaymentMethodSchema::METHOD => $method,
        PaymentMethodSchema::IS_ACTIVE => true,
        PaymentMethodSchema::SORT_ORDER => $sortOrder,
    ]);
}

it('lists active methods that have a registered, configured driver', function () {
    config([
        'payment.stripe.secret' => 'sk_test_x',
        'payment.stripe.webhook_secret' => 'whsec_test_x',
    ]);
    activeListedMethod('manual', 10);
    activeListedMethod('stripe', 20);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual', 'stripe']);
});

it('orders the checkout picker by the admin-set position', function () {
    config([
        'payment.stripe.secret' => 'sk_test_x',
        'payment.stripe.webhook_secret' => 'whsec_test_x',
    ]);
    activeListedMethod('manual', 20);
    activeListedMethod('stripe', 10);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['stripe', 'manual']);
});

it('hides a method an admin deactivated', function () {
    config([
        'payment.stripe.secret' => 'sk_test_x',
        'payment.stripe.webhook_secret' => 'whsec_test_x',
    ]);
    activeListedMethod('manual');
    PaymentMethod::query()->create([
        PaymentMethodSchema::METHOD => 'stripe',
        PaymentMethodSchema::IS_ACTIVE => false,
        PaymentMethodSchema::SORT_ORDER => 20,
    ]);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual']);
});

it('treats a method without a row (a new driver) as disabled', function () {
    config([
        'payment.stripe.secret' => 'sk_test_x',
        'payment.stripe.webhook_secret' => 'whsec_test_x',
    ]);
    activeListedMethod('manual');
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual']);
});

it('hides methods whose driver is not registered', function () {
    config([
        'payment.drivers' => ['manual' => ManualPaymentDriver::class],
        'payment.stripe.secret' => 'sk_test_x',
        'payment.stripe.webhook_secret' => 'whsec_test_x',
    ]);
    activeListedMethod('manual');
    activeListedMethod('stripe');
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual']);
});

it('hides a gateway whose credentials are missing', function () {
    config([
        'payment.stripe.secret' => null,
        'payment.stripe.webhook_secret' => null,
    ]);
    activeListedMethod('manual');
    activeListedMethod('stripe');
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual']);
});

it('offers an extension-registered method like a built-in one', function () {
    // How a marketplace/extension driver arrives: registered in config,
    // declaring its own credentials — no core code knows the slug.
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
        'payment.sandbox.api_key' => 'sandbox-key',
    ]);
    activeListedMethod('manual', 20);
    activeListedMethod('sandbox', 10);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['sandbox', 'manual']);
});

it('rejects unauthenticated callers', function () {
    $this->getJson($this->baseUrl('/user/methods'))->assertUnauthorized();
});
