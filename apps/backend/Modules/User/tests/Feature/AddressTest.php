<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Core\Models\Country;
use Modules\User\Models\Address;
use Modules\User\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function country(string $code): Country
{
    return Country::query()->firstOrCreate(
        ['code' => $code],
        ['code3' => 'USA', 'name' => $code],
    );
}

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'shipping',
        'full_name' => 'Jane Doe',
        'phone' => '+123456789',
        'address_line' => '1 Main St',
        'city' => 'Springfield',
        'postal_code' => '12345',
        'country_code' => 'US',
        'is_default' => true,
    ], $overrides);
}

it('runs the address CRUD happy path', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    country('US');

    $addressId = $this->postJson($this->baseUrl('/user/addresses'), addressPayload())
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Jane Doe')
        ->json('data.id');

    $this->getJson($this->baseUrl('/user/addresses'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->patchJson($this->baseUrl('/user/addresses/'.$addressId), addressPayload(['city' => 'Shelbyville']))
        ->assertOk()
        ->assertJsonPath('data.city', 'Shelbyville');

    $this->deleteJson($this->baseUrl('/user/addresses/'.$addressId))
        ->assertNoContent();

    expect(Address::query()->count())->toBe(0);
});

it('keeps addresses private to their owner', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    country('US');

    $address = Address::factory()->create([
        'user_id' => $owner->id,
        'country_code' => 'US',
    ]);

    $this->actingAs($intruder)
        ->getJson($this->baseUrl('/user/addresses/'.$address->id))
        ->assertStatus(404);

    $this->actingAs($intruder)
        ->patchJson($this->baseUrl('/user/addresses/'.$address->id), addressPayload())
        ->assertStatus(404);

    expect($address->refresh()->city)->toBe($address->city);
});

it('rejects an address with an unknown country', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson($this->baseUrl('/user/addresses'), addressPayload(['country_code' => 'ZZ']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'http.422');

    expect(Address::count())->toBe(0);
});

it('moves the default flag when a new default address is created', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    country('US');

    $first = $this->postJson($this->baseUrl('/user/addresses'), addressPayload())
        ->assertCreated()->json('data.id');

    $this->postJson($this->baseUrl('/user/addresses'), addressPayload([
        'address_line' => '2 Other St',
        'is_default' => true,
    ]))->assertCreated();

    expect(Address::query()->find($first)->is_default)->toBeFalse()
        ->and(Address::query()->where('is_default', true)->count())->toBe(1);
});
