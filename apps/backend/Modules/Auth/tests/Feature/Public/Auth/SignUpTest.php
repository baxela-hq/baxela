<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

$endpoint = '/public/auth/signup';

it($endpoint.' returns 201 with valid data', function () use ($endpoint) {

    $password = fake()->password(8);
    // @see Modules\Auth\Http\Requests\User\Auth\SignUpRequest;
    $data = [
        UserSchema::PASSWORD => $password,
        UserSchema::PASSWORD.'_confirmation' => $password,
        UserSchema::EMAIL => fake()->email,
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(201);
});

it($endpoint.' returns 422 with invalid data', function () use ($endpoint) {
    $password = fake()->password(8);
    // @see Modules\Auth\Http\Requests\User\Auth\SignUpRequest;
    $data = [
        //        UserSchema::PASSWORD => $password,
        UserSchema::PASSWORD.'_confirmation' => $password,
        UserSchema::EMAIL => fake()->email,
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(422);
});
