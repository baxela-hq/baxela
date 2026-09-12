<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
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
