<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Models\User;
use Modules\Core\Http\Middleware\IdempotencyMiddleware;
use Modules\Core\Models\IdempotencyKey;

uses(RefreshDatabase::class);

/**
 * A throwaway write endpoint wearing the middleware, so the behaviour is
 * exercised over HTTP without coupling this test to another module's routes.
 */
class IdempotencySideEffectCounter
{
    public static int $executions = 0;
}

beforeEach(function () {
    IdempotencySideEffectCounter::$executions = 0;

    Route::post('_testing/idempotent-write', function () {
        IdempotencySideEffectCounter::$executions++;

        return response()->json(['data' => ['created' => true]]);
    })->middleware(IdempotencyMiddleware::class);
});

it('replays the stored response while the side effect runs once', function () {
    $this->actingAs(User::factory()->create());
    $headers = ['X-Idempotency-Key' => 'order-checkout-1'];

    $first = $this->postJson('_testing/idempotent-write', [], $headers);
    $second = $this->postJson('_testing/idempotent-write', [], $headers);

    $first->assertOk();
    $second->assertOk();

    expect($first->json())->toBe($second->json())
        ->and(IdempotencySideEffectCounter::$executions)->toBe(1)
        ->and(IdempotencyKey::count())->toBe(1)
        ->and($second->headers->get(IdempotencyMiddleware::IDEMPOTENCY_RESPONSE_KEY))->toBe('true');
});

it('executes again for a different idempotency key', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('_testing/idempotent-write', [], ['X-Idempotency-Key' => 'key-one'])->assertOk();
    $this->postJson('_testing/idempotent-write', [], ['X-Idempotency-Key' => 'key-two'])->assertOk();

    expect(IdempotencySideEffectCounter::$executions)->toBe(2)
        ->and(IdempotencyKey::count())->toBe(2);
});

it('never engages without the header', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('_testing/idempotent-write')->assertOk();
    $this->postJson('_testing/idempotent-write')->assertOk();

    expect(IdempotencySideEffectCounter::$executions)->toBe(2)
        ->and(IdempotencyKey::count())->toBe(0);
});
