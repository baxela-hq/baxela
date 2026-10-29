<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Order\Models\Order;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;
use Modules\Shipping\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    $this->actingAs($this->superAdminUser());
    // HelperTrait fixtures join through the countries table; method
    // validation resolves translations against active languages
    Modules\Core\Models\Country::firstOrCreate(
        ['code' => 'US'],
        ['code3' => 'USA', 'name' => 'United States'],
    );
    Modules\Core\Models\Language::firstOrCreate(
        ['code' => 'en'],
        ['locale' => 'en', 'name' => 'English', 'code3' => 'eng', 'is_default' => true, 'is_active' => true],
    );
});

function zonePayload(array $override = []): array
{
    return array_merge([
        ZoneSchema::NAME => 'Domestic',
        ZoneSchema::IS_ACTIVE => true,
        ZoneSchema::COUNTRY_CODES => ['US'],
    ], $override);
}

it('creates, shows, updates and deletes a zone', function () {
    $zone = $this->postJson($this->baseUrl('/admin/zones'), zonePayload())
        ->assertCreated()->json('data.id');

    $this->getJson($this->baseUrl('/admin/zones'))->assertOk();
    $this->getJson($this->baseUrl('/admin/zones/'.$zone))->assertOk();

    $this->patchJson($this->baseUrl('/admin/zones/'.$zone), zonePayload([
        ZoneSchema::NAME => 'Renamed',
        ZoneSchema::IS_ACTIVE => false,
    ]))->assertOk();

    expect(Zone::query()->find($zone)->{ZoneSchema::NAME})->toBe('Renamed');

    $this->deleteJson($this->baseUrl('/admin/zones/'.$zone))->assertNoContent();
    expect(Zone::query()->whereKey($zone)->exists())->toBeFalse();
});

it('rejects a zone without a name', function () {
    $this->postJson($this->baseUrl('/admin/zones'), zonePayload([
        ZoneSchema::NAME => null,
    ]))->assertStatus(422);
});

it('creates a method with translations and refuses a duplicate code', function () {
    $lang = \Illuminate\Support\Facades\App::make(\Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface::class)
        ->getLanguageIdByCode('en') ?? 1;

    $payload = [
        MethodSchema::CODE => 'express',
        MethodSchema::IS_ACTIVE => true,
        MethodSchema::RES_TRANSLATIONS => [
            ['language' => 'en', 'language_id' => $lang, 'name' => 'Express'],
        ],
    ];

    $method = $this->postJson($this->baseUrl('/admin/methods'), $payload)
        ->assertCreated()->json('data.id');
    expect(Method::query()->find($method))->not->toBeNull();

    // unique code guard
    $this->postJson($this->baseUrl('/admin/methods'), $payload)->assertStatus(422);

    $this->patchJson($this->baseUrl('/admin/methods/'.$method), [
        MethodSchema::CODE => 'express',
        MethodSchema::IS_ACTIVE => false,
        MethodSchema::RES_TRANSLATIONS => [
            ['language' => 'en', 'language_id' => $lang, 'name' => 'Express 2-day'],
        ],
    ])->assertOk();

    $this->deleteJson($this->baseUrl('/admin/methods/'.$method))->assertNoContent();
    expect(Method::query()->whereKey($method)->exists())->toBeFalse();
});

it('prices a method per zone and enforces one rate per pair', function () {
    $zone = $this->postJson($this->baseUrl('/admin/zones'), zonePayload())->json('data.id');
    $method = Method::factory()->create()->id;

    $this->postJson($this->baseUrl('/admin/rates'), [
        RateSchema::METHOD_ID => $method,
        RateSchema::ZONE_ID => $zone,
        RateSchema::PRICE => 5.5,
    ])->assertCreated();

    // the (method, zone) pair is unique — a second rate for it is rejected
    $this->postJson($this->baseUrl('/admin/rates'), [
        RateSchema::METHOD_ID => $method,
        RateSchema::ZONE_ID => $zone,
        RateSchema::PRICE => 9,
    ])->assertStatus(422);

    $rate = Rate::query()->where(RateSchema::METHOD_ID, $method)->first();
    $this->patchJson($this->baseUrl('/admin/rates/'.$rate->id), [
        RateSchema::METHOD_ID => $method,
        RateSchema::ZONE_ID => $zone,
        RateSchema::PRICE => 6,
    ])->assertOk();
    $this->deleteJson($this->baseUrl('/admin/rates/'.$rate->id))->assertNoContent();
});

it('creates a shipment, walks legal transitions and blocks illegal ones', function () {
    // Order factory randomizes statuses — pin a state the shipment
    // sync accepts (paid + pending fulfillment)
    $order = Order::factory()->create([
        'status' => 'pending',
        'payment_status' => 'paid',
    ]);
    $shipment = $this->postJson($this->baseUrl('/admin/shipments'), [
        ShipmentSchema::ORDER_ID => $order->id,
        ShipmentSchema::CARRIER_NAME => 'DHL',
    ])->assertCreated()->json('data.id');

    expect(Shipment::find($shipment)->{ShipmentSchema::STATUS}->value)->toBe('pending');

    // pending → packed → shipped is legal (order_id is required on every write)
    $this->patchJson($this->baseUrl('/admin/shipments/'.$shipment), [
        ShipmentSchema::ORDER_ID => $order->id,
        ShipmentSchema::STATUS => 'packed',
    ])->assertOk();
    $this->patchJson($this->baseUrl('/admin/shipments/'.$shipment), [
        ShipmentSchema::ORDER_ID => $order->id,
        ShipmentSchema::STATUS => 'shipped',
        ShipmentSchema::TRACKING_NUMBER => 'JD0146000038342',
    ])->assertOk();

    // shipped → packed is not
    $this->patchJson($this->baseUrl('/admin/shipments/'.$shipment), [
        ShipmentSchema::ORDER_ID => $order->id,
        ShipmentSchema::STATUS => 'packed',
    ])->assertStatus(400);

    $this->getJson($this->baseUrl('/admin/shipments'))->assertOk();
    $this->getJson($this->baseUrl('/admin/shipments/'.$shipment))->assertOk();
});

it('denies the whole admin surface to a user without shipping permissions', function () {
    $this->actingAs(User::factory()->create(['is_active' => true]));

    $this->getJson($this->baseUrl('/admin/zones'))->assertStatus(403);
    $this->postJson($this->baseUrl('/admin/zones'), zonePayload())->assertStatus(403);
    $this->getJson($this->baseUrl('/admin/methods'))->assertStatus(403);
});
