<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('resolves the manual driver by method and by name', function () {
    $manager = app(PaymentDriverManager::class);

    expect($manager->forMethod(PaymentMethodEnum::MANUAL))->toBeInstanceOf(ManualPaymentDriver::class)
        ->and($manager->forName('manual'))->toBeInstanceOf(ManualPaymentDriver::class);
});

it('throws for a method without a configured driver', function (PaymentMethodEnum $method) {
    $manager = app(PaymentDriverManager::class);

    expect(fn () => $manager->forMethod($method))->toThrow(PaymentException::class);
})->with([
    'stripe' => [PaymentMethodEnum::STRIPE],
    'paypal' => [PaymentMethodEnum::PAYPAL],
]);

it('throws for an unknown driver name', function () {
    expect(fn () => app(PaymentDriverManager::class)->forName('crypto'))
        ->toThrow(PaymentException::class);
});
