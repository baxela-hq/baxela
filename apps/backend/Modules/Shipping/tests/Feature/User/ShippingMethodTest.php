<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Core\Models\Language;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\MethodTranslation;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Tests\Feature\HelperTrait;
use Modules\User\Models\Address;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function addressIn(User $user, string $countryCode): Address
{
    return Address::factory()->create([
        'user_id' => $user->id,
        'country_code' => $countryCode,
    ]);
}

it('lists quoted methods for the address country', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $zone = $this->activeZone('US');
    $method = $this->activeMethod($zone, 9.5);

    $this->getJson($this->baseUrl('/user/methods?address_id='.addressIn($user, 'US')->id))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $method->id)
        ->assertJsonPath('data.0.price', 9.5);
});

it('rejects a quote request for an address the user does not own', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->activeZone('US');

    $this->getJson($this->baseUrl('/user/methods?address_id='.addressIn(User::factory()->create(), 'US')->id))
        ->assertStatus(400)
        ->assertJsonPath('code', 'shipping.method.invalid_address');
});

it('quotes method names in the visitor language, falling back to the default', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $en = Language::factory()->create(['code' => 'en', 'is_default' => false]);
    $fa = Language::factory()->create(['code' => 'fa', 'is_default' => true]);

    $zone = $this->activeZone('US');
    $method = Method::factory()->create([MethodSchema::IS_ACTIVE => true]);
    foreach ([[$en, 'Express'], [$fa, 'فوری']] as [$language, $name]) {
        MethodTranslation::query()->create([
            MethodTranslationSchema::METHOD_ID => $method->id,
            MethodTranslationSchema::LANGUAGE_ID => $language->id,
            MethodTranslationSchema::NAME => $name,
        ]);
    }
    Rate::factory()->create([
        RateSchema::METHOD_ID => $method->id,
        RateSchema::ZONE_ID => $zone->id,
        RateSchema::PRICE => 9.5,
    ]);

    $url = $this->baseUrl('/user/methods?address_id='.addressIn($user, 'US')->id);

    $this->getJson($url, ['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Express');

    // Laravel's test client always sends an implicit Accept-Language (en), so
    // exercise the fallback with a language the store does not have
    $this->getJson($url, ['Accept-Language' => 'fr-FR,fr;q=0.9'])
        ->assertOk()
        ->assertJsonPath('data.0.name', 'فوری');
});
