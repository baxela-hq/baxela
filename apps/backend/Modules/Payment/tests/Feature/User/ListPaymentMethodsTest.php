<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('lists the methods that have a registered driver', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())
        ->toEqualCanonicalizing(['manual', 'stripe']);
});

it('hides methods whose driver is not registered', function () {
    config(['payment.drivers' => ['manual' => ManualPaymentDriver::class]]);
    $this->actingAs(User::factory()->create());

    $response = $this->getJson($this->baseUrl('/user/methods'))->assertOk();

    expect(collect($response->json('data'))->pluck('method')->all())->toBe(['manual']);
});

it('rejects unauthenticated callers', function () {
    $this->getJson($this->baseUrl('/user/methods'))->assertUnauthorized();
});
