<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Support\Google\SignInNonceService;

uses(RefreshDatabase::class);

function nonceService(): SignInNonceService
{
    return app(SignInNonceService::class);
}

function nonceKey(string $nonce): string
{
    return 'auth:google:nonce:'.hash('sha256', $nonce);
}

it('issues a nonce and stores it hashed, never plaintext', function () {
    $nonce = nonceService()->issue();

    expect($nonce)->toBeString()->toHaveLength(43)
        // The plaintext nonce must never be a Redis key or value.
        ->and(Redis::connection()->exists('auth:google:nonce:'.$nonce))->toBe(0)
        ->and(Redis::connection()->exists(nonceKey($nonce)))->toBe(1);
});

it('consumes a nonce exactly once, atomically', function () {
    $nonce = nonceService()->issue();

    expect(nonceService()->consume($nonce))->toBeTrue()
        ->and(Redis::connection()->exists(nonceKey($nonce)))->toBe(0)
        ->and(nonceService()->consume($nonce))->toBeFalse();
});

it('rejects a nonce that was never issued', function () {
    expect(nonceService()->consume('never-issued-value-1234567890-1234567890'))->toBeFalse();
});

it('expires nonces after the configured TTL', function () {
    config(['auth.google.nonce_ttl' => 1]);

    $nonce = nonceService()->issue();

    sleep(2);

    expect(nonceService()->consume($nonce))->toBeFalse();
});

it('consumes nonces across service instances sharing one store', function () {
    // Simulates a second application instance: a fresh service instance
    // over the same shared Redis store must see the same single use.
    $nonce = nonceService()->issue();

    $secondInstance = new SignInNonceService(app(Illuminate\Contracts\Redis\Factory::class));

    expect($secondInstance->consume($nonce))->toBeTrue()
        ->and(nonceService()->consume($nonce))->toBeFalse()
        ->and($secondInstance->consume($nonce))->toBeFalse();
});

it('fails closed when Redis is unavailable', function () {
    $factory = Mockery::mock(Illuminate\Contracts\Redis\Factory::class);
    $connection = Mockery::mock(Connection::class);
    $factory->shouldReceive('connection')->andThrow(new RuntimeException('redis down'));
    $this->app->instance(Illuminate\Contracts\Redis\Factory::class, $factory);
    $service = new SignInNonceService($factory);

    try {
        $service->issue();
        $this->fail('issue() must fail closed');
    } catch (AuthException $e) {
        expect($e->code)->toBe('auth.google.unavailable')
            ->and($e->httpStatus)->toBe(503);
    }

    try {
        $service->consume('whatever-nonce-value-1234567890-123456');
        $this->fail('consume() must fail closed');
    } catch (AuthException $e) {
        expect($e->getCode())->toBe('auth.google.unavailable');
    }
});
