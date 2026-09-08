<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

$signInEndpoint = '/public/auth/signin';
$resetPasswordEndpoint = '/public/auth/reset-password/request';

it($signInEndpoint.' returns 429 with too many attempts from one ip', function () use ($signInEndpoint) {
    config(['auth.rate_limit.sign_in' => 2]);

    $data = [
        UserSchema::EMAIL => fake()->email(),
        UserSchema::PASSWORD => 'fake_password',
    ];

    $first = $this->postJson($this->baseUrl($signInEndpoint), $data);
    $second = $this->postJson($this->baseUrl($signInEndpoint), $data);
    $third = $this->postJson($this->baseUrl($signInEndpoint), $data);

    expect($first->status())->not->toBe(429)
        ->and($second->status())->not->toBe(429);

    $third->assertStatus(429);
    $third->assertJsonPath('code', 'http.429');
    expect($third->headers->get('Retry-After'))->not->toBeNull();
});

it($resetPasswordEndpoint.' returns 429 with too many attempts from one ip', function () use ($resetPasswordEndpoint) {
    config(['auth.rate_limit.otp_request' => 2]);

    $data = [
        UserSchema::EMAIL => fake()->email(),
    ];

    $first = $this->postJson($this->baseUrl($resetPasswordEndpoint), $data);
    $second = $this->postJson($this->baseUrl($resetPasswordEndpoint), $data);
    $third = $this->postJson($this->baseUrl($resetPasswordEndpoint), $data);

    expect($first->status())->not->toBe(429)
        ->and($second->status())->not->toBe(429);

    $third->assertStatus(429);
    $third->assertJsonPath('code', 'http.429');
});
