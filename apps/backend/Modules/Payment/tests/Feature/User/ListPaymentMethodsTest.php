<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function activeListedMethod(PaymentMethodEnum $method, int $sortOrder = 0): PaymentMethod
{
    return PaymentMethod::query()->create([
        PaymentMethodSchema::METHOD => $method,
        PaymentMethodSchema::IS_ACTIVE => true,
        PaymentMethodSchema::SORT_ORDER => $sortOrder,
    ]);
}

it('lists active methods that have a registered, configured driver', function () {
    config(['payment.stripe.secret' => 'sk_test_x']);
    activeListedMethod(PaymentMethodEnum::MANUAL, 10);
    activeListedMethod(PaymentMethodEnum::STRIPE, 20);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual', 'stripe']);
});

it('orders the checkout picker by the admin-set position', function () {
    config(['payment.stripe.secret' => 'sk_test_x']);
    activeListedMethod(PaymentMethodEnum::MANUAL, 20);
    activeListedMethod(PaymentMethodEnum::STRIPE, 10);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['stripe', 'manual']);
});

it('hides a method an admin deactivated', function () {
    config(['payment.stripe.secret' => 'sk_test_x']);
    activeListedMethod(PaymentMethodEnum::MANUAL);
    PaymentMethod::query()->create([
        PaymentMethodSchema::METHOD => PaymentMethodEnum::STRIPE,
        PaymentMethodSchema::IS_ACTIVE => false,
        PaymentMethodSchema::SORT_ORDER => 20,
    ]);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual']);
});

it('treats a method without a row (a new driver) as disabled', function () {
    config(['payment.stripe.secret' => 'sk_test_x']);
    activeListedMethod(PaymentMethodEnum::MANUAL);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual']);
});

it('hides methods whose driver is not registered', function () {
    config([
        'payment.drivers' => ['manual' => ManualPaymentDriver::class],
        'payment.stripe.secret' => 'sk_test_x',
    ]);
    activeListedMethod(PaymentMethodEnum::MANUAL);
    activeListedMethod(PaymentMethodEnum::STRIPE);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual']);
});

it('hides a gateway whose credentials are missing', function () {
    config(['payment.stripe.secret' => null]);
    activeListedMethod(PaymentMethodEnum::MANUAL);
    activeListedMethod(PaymentMethodEnum::STRIPE);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual']);
});

it('rejects unauthenticated callers', function () {
    $this->getJson($this->baseUrl('/user/methods'))->assertUnauthorized();
});
