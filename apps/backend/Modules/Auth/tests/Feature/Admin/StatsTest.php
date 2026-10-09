<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Stats\StatsSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function customer(?string $createdAt = null): User
{
    $user = User::factory()->create();

    if ($createdAt !== null) {
        $user->forceFill([UserSchema::CREATED_AT => $createdAt])->save();
    }

    return $user;
}

it('shows customer stats for an admin, excluding role-bearing users', function () {
    customer();
    customer();
    customer(now()->subMonth()->startOfMonth()->addDay()->format('Y-m-d H:i:s'));

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/stats'))
        ->assertOk()
        ->assertJsonPath('data.'.StatsSchema::RES_CUSTOMERS_COUNT, 3)
        ->assertJsonPath('data.'.StatsSchema::RES_NEW_CUSTOMERS_THIS_MONTH, 2)
        // whole-number floats serialize to JSON ints (100, not 100.0)
        ->assertJsonPath('data.'.StatsSchema::RES_CUSTOMERS_CHANGE_PERCENT, 100);
});

it('nulls the change percent when the previous month has no new customers', function () {
    customer();

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/stats'))
        ->assertOk()
        ->assertJsonPath('data.'.StatsSchema::RES_CUSTOMERS_COUNT, 1)
        ->assertJsonPath('data.'.StatsSchema::RES_CUSTOMERS_CHANGE_PERCENT, null);
});

it('requires authentication', function () {
    $this->getJson($this->baseUrl('/admin/stats'))->assertUnauthorized();
});
