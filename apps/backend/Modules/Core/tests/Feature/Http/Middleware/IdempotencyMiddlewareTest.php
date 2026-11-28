<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Tenant\IdempotencyUnresolvedEvent;
use Modules\Core\Http\Middleware\IdempotencyMiddleware;
use Modules\Core\Models\IdempotencyKey;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;
use Modules\Core\Support\Tenancy\IdempotencyCommitMarker;
use Modules\Core\Support\Tenancy\IdempotencyIdentity;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Core\Tests\Feature\Http\Middleware\IdempotencySideEffectCounter;

uses(RefreshDatabase::class);

beforeEach(function () {
    IdempotencySideEffectCounter::$executions = 0;

    Route::post('_testing/idempotent-write', function (Request $request) {
        IdempotencySideEffectCounter::$executions++;

        return response()->json(['data' => ['created' => true]]);
    })->middleware(IdempotencyMiddleware::class);

    Route::post('_testing/committed-response-failed', function (Request $request) {
        IdempotencySideEffectCounter::$executions++;
        DB::transaction(static fn () => IdempotencyCommitMarker::mark($request));

        return response()->json(['error' => 'downstream'], 500);
    })->middleware(IdempotencyMiddleware::class);

    Route::post('_testing/stale-completion', function (Request $request) {
        IdempotencySideEffectCounter::$executions++;
        /** @var array{id: int} $claim */
        $claim = $request->attributes->get(IdempotencyMiddleware::CLAIM_ATTRIBUTE);

        // Another claimant took the key while we were running.
        IdempotencyKey::query()->whereKey($claim['id'])->update([
            IdempotencyKeysSchema::CLAIM_TOKEN => 'new-owner-token',
            IdempotencyKeysSchema::STATUS => IdempotencyKeysSchema::STATUS_COMPLETED,
            IdempotencyKeysSchema::RESPONSE => ['data' => ['owner' => 'new']],
        ]);

        return response()->json(['data' => ['owner' => 'old']]);
    })->middleware(IdempotencyMiddleware::class);

    Route::post('_testing/stale-marker', function (Request $request) {
        /** @var array{id: int} $claim */
        $claim = $request->attributes->get(IdempotencyMiddleware::CLAIM_ATTRIBUTE);

        // The ownership change is already committed when the marker runs.
        IdempotencyKey::query()->whereKey($claim['id'])->update([
            IdempotencyKeysSchema::CLAIM_TOKEN => 'new-owner-token',
        ]);

        DB::transaction(function () use ($request) {
            User::factory()->create();
            IdempotencyCommitMarker::mark($request);
        });

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

it('refuses a second request while a claim is still in progress', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    IdempotencyKey::factory()->create([
        IdempotencyKeysSchema::KEY => 'in-progress',
        IdempotencyKeysSchema::PRINCIPAL_KEY => 'user:'.$user->id,
        IdempotencyKeysSchema::EXPIRES_AT => now()->addMinutes(5),
    ]);

    $this->postJson('_testing/idempotent-write', [], ['X-Idempotency-Key' => 'in-progress'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'core.idempotency.in_progress');

    expect(IdempotencySideEffectCounter::$executions)->toBe(0)
        ->and(IdempotencyKey::count())->toBe(1);
});

it('lets the same key be used independently by another tenant', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $headers = ['X-Idempotency-Key' => 'shared-key'];

    $this->postJson('_testing/idempotent-write', [], $headers)->assertOk();

    app(TenantContext::class)->runForTenant(2, function () use ($headers) {
        $this->postJson('_testing/idempotent-write', [], $headers)->assertOk();
    });

    expect(IdempotencySideEffectCounter::$executions)->toBe(2)
        ->and(IdempotencyKey::query()->where('tenant_id', 1)->count())->toBe(1)
        ->and(IdempotencyKey::withoutGlobalScopes()->where('tenant_id', 2)->count())->toBe(1);
});

it('dedupes guests by hashed cart token, not by IP or a nullable principal', function () {
    $tokenA = (string) Str::uuid();
    $tokenB = (string) Str::uuid();
    $headersA = ['X-Idempotency-Key' => 'cart-key', 'X-Cart-Token' => $tokenA];
    $headersB = ['X-Idempotency-Key' => 'cart-key', 'X-Cart-Token' => $tokenB];

    $this->postJson('_testing/idempotent-write', [], $headersA)->assertOk();
    $this->postJson('_testing/idempotent-write', [], $headersA)->assertOk();
    $this->postJson('_testing/idempotent-write', [], $headersB)->assertOk();

    $principals = IdempotencyKey::query()->pluck(IdempotencyKeysSchema::PRINCIPAL_KEY)->all();
    sort($principals);
    $expected = ['guest:'.hash('sha256', $tokenA), 'guest:'.hash('sha256', $tokenB)];
    sort($expected);

    expect(IdempotencySideEffectCounter::$executions)->toBe(2)
        ->and($principals)->toHaveCount(2)
        ->and($principals)->toBe($expected)
        ->and(IdempotencyKey::query()->whereNull(IdempotencyKeysSchema::PRINCIPAL_KEY)->count())->toBe(0);
});

it('refuses to reclaim an expired claim whose business work committed', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $claim = IdempotencyKey::factory()->create([
        IdempotencyKeysSchema::KEY => 'committed-key',
        IdempotencyKeysSchema::PRINCIPAL_KEY => 'user:'.$user->id,
        IdempotencyKeysSchema::EXPIRES_AT => now()->subMinute(),
    ]);
    $claim->forceFill([IdempotencyKeysSchema::COMMITTED_AT => now()])->save();

    Event::fake([IdempotencyUnresolvedEvent::class]);

    $this->postJson('_testing/idempotent-write', [], ['X-Idempotency-Key' => 'committed-key'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'core.idempotency.unresolved');

    expect(IdempotencySideEffectCounter::$executions)->toBe(0)
        ->and(IdempotencyKey::count())->toBe(1);

    Event::assertDispatched(IdempotencyUnresolvedEvent::class);
});

it('keeps a committed claim for operator recovery when the response fails', function () {
    $this->actingAs(User::factory()->create());

    $failed = $this->postJson('_testing/committed-response-failed', [], ['X-Idempotency-Key' => 'failed-response']);
    $failed->assertStatus(500);

    $claim = IdempotencyKey::query()->firstOrFail();

    expect($claim->{IdempotencyKeysSchema::COMMITTED_AT})->not->toBeNull()
        ->and($claim->{IdempotencyKeysSchema::STATUS})->toBe(IdempotencyKeysSchema::STATUS_PROCESSING);

    $retry = $this->postJson('_testing/idempotent-write', [], ['X-Idempotency-Key' => 'failed-response']);
    $retry->assertStatus(409)->assertJsonPath('code', 'core.idempotency.unresolved');

    expect(IdempotencySideEffectCounter::$executions)->toBe(1)
        ->and(IdempotencyKey::count())->toBe(1);
});

it('rejects a stale claimant completion and preserves the new owner result', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('_testing/stale-completion', [], ['X-Idempotency-Key' => 'fenced'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'core.idempotency.unresolved');

    $claim = IdempotencyKey::query()->firstOrFail();

    expect($claim->{IdempotencyKeysSchema::STATUS})->toBe(IdempotencyKeysSchema::STATUS_COMPLETED)
        ->and($claim->{IdempotencyKeysSchema::CLAIM_TOKEN})->toBe('new-owner-token')
        ->and($claim->{IdempotencyKeysSchema::RESPONSE})->toBe(['data' => ['owner' => 'new']]);
});

it('rolls business work back when the commit marker loses ownership', function () {
    $this->actingAs(User::factory()->create());
    $usersBefore = User::count();

    $this->postJson('_testing/stale-marker', [], ['X-Idempotency-Key' => 'stale-marker'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'core.idempotency.unresolved');

    $claim = IdempotencyKey::query()->firstOrFail();

    expect(User::count())->toBe($usersBefore)
        ->and($claim->{IdempotencyKeysSchema::CLAIM_TOKEN})->toBe('new-owner-token')
        ->and($claim->{IdempotencyKeysSchema::COMMITTED_AT})->toBeNull();
});

it('derives provider keys from the logical identity, never the claim token', function () {
    $user = User::factory()->create();
    $user2 = User::factory()->create();
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_X-IDEMPOTENCY-KEY' => 'provider-key',
        'HTTP_X-CART-TOKEN' => (string) Str::uuid(),
    ]);
    $request->setUserResolver(static fn () => $user);

    $first = IdempotencyIdentity::providerKey($request, 'stripe');
    $request2 = Request::create('/', 'POST', [], [], [], ['HTTP_X-IDEMPOTENCY-KEY' => 'provider-key']);
    $request2->setUserResolver(static fn () => $user2);

    expect($first)->toBeString()
        // A different claim token does not change the external key.
        ->and(IdempotencyIdentity::providerKey($request, 'stripe'))->toBe($first)
        // Nor does a different user or provider.
        ->and(IdempotencyIdentity::providerKey($request2, 'stripe'))->not->toBe($first)
        ->and(IdempotencyIdentity::providerKey($request, 'paypal'))->not->toBe($first);

    app(TenantContext::class)->runForTenant(2, function () use ($request, $first) {
        expect(IdempotencyIdentity::providerKey($request, 'stripe'))->not->toBe($first);
    });
});
