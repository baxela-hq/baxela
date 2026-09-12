<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\User\Models\Profile;
use Modules\User\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('shows an empty profile when none exists yet', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson($this->baseUrl('/user/profile'))
        ->assertOk()
        ->assertJsonPath('data.full_name', null);
});

it('creates the profile on first update and edits it afterwards', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->patchJson($this->baseUrl('/user/profile'), [
        'full_name' => 'Jane Doe',
        'display_name' => 'jane',
        'bio' => null,
        'avatar' => null,
        'gender' => 'female',
        'date_of_birth' => '1995-05-05',
    ])->assertOk()->assertJsonPath('data.full_name', 'Jane Doe');

    $this->patchJson($this->baseUrl('/user/profile'), [
        'full_name' => 'Jane Doe',
        'display_name' => 'jane-d',
        'bio' => 'Updated bio',
        'avatar' => null,
        'gender' => 'female',
        'date_of_birth' => '1995-05-05',
    ])->assertOk()->assertJsonPath('data.display_name', 'jane-d');

    expect(Profile::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Profile::query()->where('user_id', $user->id)->first()->bio)->toBe('Updated bio');
});

it('isolates profiles between users', function () {
    $owner = User::factory()->create();
    Profile::factory()->create(['user_id' => $owner->id, 'full_name' => 'Owner Name']);

    $this->actingAs(User::factory()->create())
        ->getJson($this->baseUrl('/user/profile'))
        ->assertOk()
        ->assertJsonPath('data.full_name', null);
});
