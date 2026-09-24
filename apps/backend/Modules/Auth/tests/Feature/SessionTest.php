<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Auth\Models\User;
use Modules\Auth\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function activeUser(): User
{
    return User::factory()->active()->create(['password' => 'Passw0rd!']);
}

function signInToken(User $user, array $overrides = []): string
{
    $payload = array_merge([
        'email' => $user->email,
        'password' => 'Passw0rd!',
    ], $overrides);

    return test()->postJson(test()->baseUrl('/public/auth/signin'), $payload)
        ->assertOk()
        ->json('data.token');
}

/**
 * Authenticated JSON call. Guard instances are cached for the container's
 * lifetime, and a feature test shares one container across requests — without
 * forgetting them, the user resolved by an earlier request would leak into
 * the next one and mask revoked/expired tokens.
 */
function jsonAs(string $token, string $method, string $endpoint, array $data = []): TestResponse
{
    app(AuthManager::class)->forgetGuards();

    $url = test()->baseUrl($endpoint);
    $headers = ['Authorization' => "Bearer {$token}"];

    if (strtolower($method) === 'get') {
        return test()->getJson($url, $headers);
    }

    return test()->{strtolower($method).'Json'}($url, $data, $headers);
}

it('keeps other devices signed in when a new device signs in', function () {
    $user = activeUser();
    $laptop = signInToken($user, ['device_name' => 'laptop']);
    $phone = signInToken($user, ['device_name' => 'phone']);

    jsonAs($laptop, 'get', '/user/account/me')->assertOk();
    jsonAs($phone, 'get', '/user/account/me')->assertOk();
});

it('replaces only the token of the device signing in again', function () {
    $user = activeUser();
    $oldLaptopToken = signInToken($user, ['device_name' => 'laptop']);
    $phoneToken = signInToken($user, ['device_name' => 'phone']);
    $newLaptopToken = signInToken($user, ['device_name' => 'laptop']);

    jsonAs($oldLaptopToken, 'get', '/user/account/me')->assertUnauthorized();
    jsonAs($phoneToken, 'get', '/user/account/me')->assertOk();
    jsonAs($newLaptopToken, 'get', '/user/account/me')->assertOk();

    expect($user->tokens()->count())->toBe(2);
});

it('falls back to the user agent when no device name is sent', function () {
    $user = activeUser();

    $token = $this->postJson($this->baseUrl('/public/auth/signin'), [
        'email' => $user->email,
        'password' => 'Passw0rd!',
    ], ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14)'])->assertOk()->json('data.token');

    jsonAs($token, 'get', '/user/account/me')->assertOk();

    expect($user->tokens()->pluck('name')->first())->toStartWith('Mozilla/5.0 (Linux; Android 14)');
});

it('keeps remembered tokens valid for thirty days', function () {
    $user = activeUser();
    $token = signInToken($user, ['device_name' => 'laptop']);

    $this->travel(29)->days();
    jsonAs($token, 'get', '/user/account/me')->assertOk();

    $this->travel(2)->days();
    jsonAs($token, 'get', '/user/account/me')->assertUnauthorized();
});

it('rejects short-lived tokens after one day', function () {
    $user = activeUser();
    $token = signInToken($user, ['device_name' => 'laptop', 'remember' => false]);

    $this->travel(23)->hours();
    jsonAs($token, 'get', '/user/account/me')->assertOk();

    $this->travel(2)->hours();
    jsonAs($token, 'get', '/user/account/me')->assertUnauthorized();
});

it('revokes only the current session on sign-out', function () {
    $user = activeUser();
    $laptop = signInToken($user, ['device_name' => 'laptop']);
    $phone = signInToken($user, ['device_name' => 'phone']);

    jsonAs($phone, 'post', '/user/account/sign-out')->assertNoContent();

    jsonAs($phone, 'get', '/user/account/me')->assertUnauthorized();
    jsonAs($laptop, 'get', '/user/account/me')->assertOk();
});

it('lists sessions and marks the current one', function () {
    $user = activeUser();
    signInToken($user, ['device_name' => 'laptop']);
    $phone = signInToken($user, ['device_name' => 'phone']);

    $response = jsonAs($phone, 'get', '/user/account/sessions')->assertOk();

    $sessions = collect($response->json('data'))->keyBy('name');

    expect($sessions)->toHaveCount(2)
        ->and($sessions['phone']['is_current'])->toBeTrue()
        ->and($sessions['laptop']['is_current'])->toBeFalse()
        ->and($sessions['phone']['expires_at'])->not->toBeNull();
});

it('revokes a specific session and rejects foreign session ids', function () {
    $user = activeUser();
    $laptop = signInToken($user, ['device_name' => 'laptop']);
    $phone = signInToken($user, ['device_name' => 'phone']);

    $laptopSessionId = $user->tokens()->where('name', 'laptop')->value('id');

    jsonAs($phone, 'delete', "/user/account/sessions/{$laptopSessionId}")->assertNoContent();

    jsonAs($laptop, 'get', '/user/account/me')->assertUnauthorized();
    jsonAs($phone, 'get', '/user/account/me')->assertOk();

    $foreignUser = activeUser();
    signInToken($foreignUser, ['device_name' => 'foreign-device']);
    $foreignSessionId = $foreignUser->tokens()->value('id');

    jsonAs($phone, 'delete', "/user/account/sessions/{$foreignSessionId}")->assertNotFound();
});

it('revokes every session on sign out everywhere', function () {
    $user = activeUser();
    $laptop = signInToken($user, ['device_name' => 'laptop']);
    $phone = signInToken($user, ['device_name' => 'phone']);

    jsonAs($laptop, 'delete', '/user/account/sessions')->assertNoContent();

    jsonAs($laptop, 'get', '/user/account/me')->assertUnauthorized();
    jsonAs($phone, 'get', '/user/account/me')->assertUnauthorized();
});
