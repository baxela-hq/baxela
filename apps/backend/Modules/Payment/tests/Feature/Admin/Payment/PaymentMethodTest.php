<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('denies a guest with 401', function () {
    $this->getJson($this->baseUrl('/admin/methods'))->assertStatus(401);
});

it('lazily creates disabled rows for every registered driver', function () {
    config(['payment.stripe.secret' => 'sk_test_x']);
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/methods'))->assertOk();

    $rows = collect($response->json('data'));

    expect($rows->pluck('method')->all())->toEqualCanonicalizing(['manual', 'stripe'])
        ->and(PaymentMethod::count())->toBe(2);

    $stripe = $rows->firstWhere('method', 'stripe');
    expect($stripe[PaymentMethodSchema::IS_ACTIVE])->toBeFalse()
        ->and($stripe[PaymentMethodSchema::IS_CONFIGURED])->toBeTrue()
        ->and($stripe[PaymentMethodSchema::IS_REGISTERED])->toBeTrue();

    $manual = $rows->firstWhere('method', 'manual');
    expect($manual[PaymentMethodSchema::IS_CONFIGURED])->toBeTrue();
});

it('does not create rows for drivers without an implementation', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())
        ->not->toContain(PaymentMethodEnum::PAYPAL->value)
        ->and(PaymentMethod::count())->toBe(2);
});

it('flags a gateway whose credentials are missing', function () {
    config(['payment.stripe.secret' => null]);
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/methods'))->assertOk();

    $stripe = collect($response->json('data'))->firstWhere('method', 'stripe');

    expect($stripe[PaymentMethodSchema::IS_CONFIGURED])->toBeFalse()
        ->and($stripe[PaymentMethodSchema::IS_REGISTERED])->toBeTrue();
});

it('activates a method and moves its checkout position', function () {
    $this->actingAs($this->superAdminUser());
    $this->getJson($this->baseUrl('/admin/methods'))->assertOk();

    $method = PaymentMethod::query()
        ->where(PaymentMethodSchema::METHOD, PaymentMethodEnum::STRIPE->value)
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
    $this->actingAs($this->superAdminUser());
    $method = PaymentMethod::query()->create([
        PaymentMethodSchema::METHOD => PaymentMethodEnum::PAYPAL,
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
