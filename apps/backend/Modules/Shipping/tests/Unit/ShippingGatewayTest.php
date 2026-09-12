<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shipping\Gateways\ShippingGateway;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;
use Modules\Shipping\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('quotes a method for a country whose zone carries a matching rate', function () {
    $zone = $this->activeZone('US');
    $method = $this->activeMethod($zone, 12.5);
    $gateway = app(ShippingGateway::class);

    $quote = $gateway->getQuote($method->id, 'US');

    expect($quote)->not->toBeNull()
        ->and($quote->id)->toBe($method->id)
        ->and($quote->name)->toBe('Express')
        ->and($quote->price)->toBe(12.5)
        ->and($gateway->calculateCost($method->id, 'US'))->toBe(12.5);
});

it('falls back to rest-of-world zones for unlisted countries', function () {
    $anywhere = $this->activeZone(); // no country rows — matches everything
    $method = $this->activeMethod($anywhere, 20.0);

    expect(app(ShippingGateway::class)->getQuote($method->id, 'DE'))->not->toBeNull();
});

it('returns null when no zone serves the country', function () {
    $zone = $this->activeZone('US');
    $method = $this->activeMethod($zone, 12.5);

    $gateway = app(ShippingGateway::class);

    expect($gateway->getQuote($method->id, 'DE'))->toBeNull()
        ->and($gateway->calculateCost($method->id, 'DE'))->toBeNull()
        ->and($gateway->getMethodsForCountry('DE'))->toBeArray()
        ->and($gateway->getMethodsForCountry('DE'))->toBeEmpty();
});

it('lists methods for a country, cheapest rate per method', function () {
    $usZone = $this->activeZone('US');
    $restOfWorld = $this->activeZone();

    $express = $this->activeMethod($usZone, 10.0, name: 'Express');
    $standard = $this->activeMethod($usZone, 4.0, name: 'Standard');
    // Same standard method priced higher through the fallback zone — the
    // country-specific rate must win.
    Rate::factory()->create([
        RateSchema::METHOD_ID => $standard->id,
        RateSchema::ZONE_ID => $restOfWorld->id,
        RateSchema::PRICE => 25.0,
    ]);

    $quotes = collect(app(ShippingGateway::class)->getMethodsForCountry('US'));

    expect($quotes->pluck('id'))->toHaveCount(2)
        ->and($quotes->pluck('id')->all())->toContain($express->id, $standard->id)
        ->and($quotes->firstWhere('id', $standard->id)->price)->toBe(4.0);
});

it('excludes inactive methods, zones and rates from quotes', function () {
    $zone = $this->activeZone('US');

    $dormantMethod = Method::factory()->create([MethodSchema::IS_ACTIVE => false]);
    Rate::factory()->create([
        RateSchema::METHOD_ID => $dormantMethod->id,
        RateSchema::ZONE_ID => $zone->id,
        RateSchema::PRICE => 1.0,
    ]);

    $inactiveZone = Zone::factory()->create([ZoneSchema::IS_ACTIVE => false]);
    $orphanedMethod = $this->activeMethod($inactiveZone, 2.0);

    $gateway = app(ShippingGateway::class);

    expect($gateway->getQuote($dormantMethod->id, 'US'))->toBeNull()
        ->and($gateway->getQuote($orphanedMethod->id, 'US'))->toBeNull()
        ->and($gateway->getMethodsForCountry('US'))->toBeEmpty();
});
