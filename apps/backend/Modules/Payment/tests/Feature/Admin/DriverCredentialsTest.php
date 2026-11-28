<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Core\Contracts\Events\Payment\PaymentCredentialsUpdatedEvent;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Gateways\PaypalClient;
use Modules\Payment\Gateways\StripeCheckout;
use Modules\Payment\Models\PaymentDriverCredential;
use Modules\Payment\Support\ResolvesPaymentCredentials;
use Modules\Payment\Tests\Feature\HelperTrait;

/*
 * Driver credential admin surface (marketplace P5.2). Multiple HTTP
 * calls inside one test share this process — the same in-process config
 * persistence a long-lived worker (queue worker, app server) would see:
 * config written by the credential middleware survives between calls, so
 * every lifecycle test below crosses real request boundaries (review
 * F1). The global Pest beforeEach resets the resolver's env baseline and
 * the encrypted map per test.
 */

uses(RefreshDatabase::class);
uses(HelperTrait::class);

const STRIPE_SECRET_PLAIN = 'sk_test_plain_value';
const STRIPE_WEBHOOK_PLAIN = 'whsec_test_plain_value';

/**
 * Deterministic mid-transaction failure: throw on the second credential
 * INSERT inside one PATCH (review F3) — the first row is already written
 * by then, so only a real rollback can leave the table empty.
 */
function armSecondInsertFailure(): void
{
    $seen = 0;

    DB::listen(function ($query) use (&$seen): void {
        if (str_contains($query->sql, 'insert')
            && str_contains($query->sql, 'payment_driver_credentials')
            && ++$seen === 2) {
            throw new RuntimeException('forced credential rollback');
        }
    });
}

function credentialsUrl(string $path = ''): string
{
    return test()->baseUrl('/admin/credentials'.$path);
}

function grantCredentialPermission(string $permission): void
{
    $user = User::factory()->active()->create();
    $user->givePermissionTo(
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => GuardsEnum::WEB->value,
        ])
    );
    test()->actingAs($user);
}

/**
 * @param  array<string, mixed>  $credentials
 */
function seedEnvCredentials(array $credentials): void
{
    foreach ($credentials as $key => $value) {
        config(["payment.stripe.{$key}" => $value]);
    }
}

/**
 * @param  array<string, mixed>  $data
 */
function stripeState(array $data): array
{
    return collect($data)->firstWhere('driver', 'stripe');
}

/** Methods-listing rows are keyed by 'method', not 'driver'.
 *
 * @param  array<string, mixed>  $data
 */
function stripeMethod(array $data): array
{
    return collect($data)->firstWhere('method', 'stripe');
}

it('rejects guests on the credential endpoints', function () {
    $this->getJson(credentialsUrl())->assertUnauthorized();
    $this->patchJson(credentialsUrl('/stripe'), ['secret' => 'x'])->assertUnauthorized();
    $this->deleteJson(credentialsUrl('/stripe/secret'))->assertUnauthorized();
});

it('forbids authenticated users without a credentials permission', function () {
    $this->actingAs(User::factory()->active()->create());

    $this->getJson(credentialsUrl())->assertForbidden();
    $this->patchJson(credentialsUrl('/stripe'), ['secret' => 'x'])->assertForbidden();
    $this->deleteJson(credentialsUrl('/stripe/secret'))->assertForbidden();
});

it('enforces list, update and delete permissions independently', function () {
    grantCredentialPermission('payment.admin.credentials.update');

    $this->getJson(credentialsUrl())->assertForbidden();
    $this->deleteJson(credentialsUrl('/stripe/secret'))->assertForbidden();

    grantCredentialPermission('payment.admin.credentials.delete');

    $this->patchJson(credentialsUrl('/stripe'), ['secret' => 'x'])->assertForbidden();

    grantCredentialPermission('payment.admin.credentials.list');

    // Only "list": both mutations stay forbidden.
    $this->patchJson(credentialsUrl('/stripe'), ['secret' => 'x'])->assertForbidden();
    $this->deleteJson(credentialsUrl('/stripe/secret'))->assertForbidden();
});

it('lists resolution states and never any values', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson(credentialsUrl())->assertOk();

    $stripe = stripeState($response->json('data'));

    expect($stripe['keys'])->toBe([
        'secret' => 'none',
        'webhook_secret' => 'none',
    ])
        ->and($stripe['is_configured'])->toBeFalse()
        // The plaintext never appears anywhere in the payload.
        ->and(str_contains($response->getContent(), STRIPE_SECRET_PLAIN))->toBeFalse();
});

it('stores credentials encrypted and reports stored states without values', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->patchJson(credentialsUrl('/stripe'), [
        'secret' => STRIPE_SECRET_PLAIN,
        'webhook_secret' => STRIPE_WEBHOOK_PLAIN,
    ])->assertOk();

    expect($response->json('data.keys.secret'))->toBe('stored')
        ->and($response->json('data.keys.webhook_secret'))->toBe('stored')
        ->and($response->json('data.is_configured'))->toBeTrue()
        // The plaintext never appears in the response body.
        ->and(str_contains($response->getContent(), STRIPE_SECRET_PLAIN))->toBeFalse()
        // Encrypted at rest: the raw stored column is not the plaintext.
        ->and(PaymentDriverCredential::query()->first()->getRawOriginal('value'))
        ->not->toBe(STRIPE_SECRET_PLAIN);
});

it('flips is_configured on the methods listing end-to-end', function () {
    $this->actingAs($this->superAdminUser());

    // No env, no DB rows: stripe is unconfigured everywhere.
    $before = $this->getJson($this->baseUrl('/admin/methods'))->assertOk()->json('data');
    expect(stripeMethod($before)['is_configured'])->toBeFalse();

    $this->patchJson(credentialsUrl('/stripe'), [
        'secret' => STRIPE_SECRET_PLAIN,
        'webhook_secret' => STRIPE_WEBHOOK_PLAIN,
    ])->assertOk();

    $after = $this->getJson($this->baseUrl('/admin/methods'))->assertOk()->json('data');
    expect(stripeMethod($after)['is_configured'])->toBeTrue();
});

it('keeps required-credential semantics: one stripe key is not enough', function () {
    $this->actingAs($this->superAdminUser());

    // Preserved behavior (review F7 close-out): credentialKeys() IS the
    // required-for-lifecycle set — secret without webhook_secret stays
    // unconfigured, and so does the reverse.
    $this->patchJson(credentialsUrl('/stripe'), ['secret' => STRIPE_SECRET_PLAIN])
        ->assertOk()
        ->assertJsonPath('data.keys.webhook_secret', 'none')
        ->assertJsonPath('data.is_configured', false);

    PaymentDriverCredential::query()->delete();

    $this->patchJson(credentialsUrl('/stripe'), ['webhook_secret' => STRIPE_WEBHOOK_PLAIN])
        ->assertOk()
        ->assertJsonPath('data.keys.secret', 'none')
        ->assertJsonPath('data.is_configured', false);
});

it('stops resolving a deleted credential on the next request (persistent-process simulation)', function () {
    $resolver = app(\Modules\Payment\Support\ResolvesPaymentCredentials::class);

    $this->actingAs($this->superAdminUser());

    // Request 1: the row is written through the action — the version bump
    // rekeys the cache, so the next resolve sees it (multi-tenancy §6.3).
    $this->patchJson(credentialsUrl('/stripe'), ['secret' => STRIPE_SECRET_PLAIN])->assertOk();

    expect($resolver->credential('stripe', 'secret'))->toBe(STRIPE_SECRET_PLAIN);

    // Request 2: the delete action bumps the version again — the deleted
    // credential stops resolving immediately (review F1), never lingering
    // in a process-level config write.
    $this->deleteJson(credentialsUrl('/stripe/secret'))->assertOk();

    expect($resolver->credential('stripe', 'secret'))->toBeNull()
        ->and($resolver->stateFor('stripe', 'secret'))->toBe(\Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface::KEY_STATE_NONE);
});

it('restores env fallback across requests after deletion', function () {
    seedEnvCredentials(['secret' => 'sk_env_fallback']);
    app(\Modules\Payment\Support\ResolvesPaymentCredentials::class)->refreshEnvBaseline();

    $resolver = app(\Modules\Payment\Support\ResolvesPaymentCredentials::class);

    $this->actingAs($this->superAdminUser());

    // The stored override resolves over the env value.
    $this->patchJson(credentialsUrl('/stripe'), ['secret' => STRIPE_SECRET_PLAIN])->assertOk();
    expect($resolver->credential('stripe', 'secret'))->toBe(STRIPE_SECRET_PLAIN);

    // Delete — the version bump rekeys — the env fallback is the
    // effective credential again (review F6).
    $this->deleteJson(credentialsUrl('/stripe/secret'))->assertOk();
    expect($resolver->credential('stripe', 'secret'))->toBe('sk_env_fallback');
});

it('makes an undecryptable credential fail closed for real clients', function () {
    seedEnvCredentials(['secret' => 'sk_env_fallback']);

    $this->actingAs($this->superAdminUser());

    // A row whose ciphertext no key can decrypt — inserted raw, bypassing
    // the encrypting cast (an APP_KEY-rotation signature without needing
    // to rebuild the encrypter mid-process).
    DB::table('payment_driver_credentials')->insert([
        'tenant_id' => 1,
        'driver' => 'stripe',
        'key' => 'secret',
        'value' => 'garbage-ciphertext-not-from-this-app',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson(credentialsUrl())->assertOk();
    $stripe = stripeState($response->json('data'));

    expect($stripe['keys']['secret'])->toBe('undecryptable')
        ->and($stripe['is_configured'])->toBeFalse();

    // Fail-closed for the actual client (review F2): the resolver answers
    // UNDECRYPTABLE — the environment credential is NOT available to the
    // gateway client, and the plaintext never surfaces.
    $resolver = app(\Modules\Payment\Support\ResolvesPaymentCredentials::class);
    expect($resolver->stateFor('stripe', 'secret'))->toBe('undecryptable')
        ->and($resolver->credential('stripe', 'secret'))->toBeNull();

    expect(fn () => app(StripeCheckout::class)->retrieveSession('cs_test_123'))
        ->toThrow(PaymentException::class);
});

it('falls back to env before and after a stored override (three sources)', function () {
    seedEnvCredentials([
        'secret' => 'sk_env_fallback',
        'webhook_secret' => 'whsec_env_fallback',
    ]);

    $this->actingAs($this->superAdminUser());

    // Env-only.
    $this->getJson(credentialsUrl())
        ->assertOk()
        ->assertJsonPath('data.1.keys.secret', 'env')
        ->assertJsonPath('data.1.is_configured', true);

    // DB override.
    $this->patchJson(credentialsUrl('/stripe'), [
        'secret' => STRIPE_SECRET_PLAIN,
        'webhook_secret' => STRIPE_WEBHOOK_PLAIN,
    ])->assertOk()->assertJsonPath('data.keys.secret', 'stored');

    // DELETE restores the env fallback (review F6: the override is
    // removed, not necessarily the credential).
    $this->deleteJson(credentialsUrl('/stripe/secret'))
        ->assertOk()
        ->assertJsonPath('data.keys.secret', 'env')
        ->assertJsonPath('data.is_configured', true);

    // Deleting the last stored key with env still behind it stays
    // configured — the env credential is the effective one now.
    $this->deleteJson(credentialsUrl('/stripe/webhook_secret'))
        ->assertOk()
        ->assertJsonPath('data.keys.webhook_secret', 'env')
        ->assertJsonPath('data.is_configured', true);
});

it('rolls multi-key updates back completely on failure', function () {
    $cache = Cache::spy();
    Event::fake([PaymentCredentialsUpdatedEvent::class]);

    $this->actingAs($this->superAdminUser());

    armSecondInsertFailure();

    $this->patchJson(credentialsUrl('/stripe'), [
        'secret' => STRIPE_SECRET_PLAIN,
        'webhook_secret' => STRIPE_WEBHOOK_PLAIN,
    ])->assertStatus(500);

    // No rows, no version bump, no audit event (review F3/F5).
    expect(PaymentDriverCredential::count())->toBe(0);
    Event::assertNotDispatched(PaymentCredentialsUpdatedEvent::class);
});

it('bumps the credential version only after a committed mutation', function () {
    Event::fake([PaymentCredentialsUpdatedEvent::class]);

    $this->actingAs($this->superAdminUser());

    $resolver = app(\Modules\Payment\Support\ResolvesPaymentCredentials::class);

    // A validation failure bumps nothing.
    $this->patchJson(credentialsUrl('/stripe'), ['not_declared' => 'x'])->assertStatus(422);
    $before = $resolver->credentialVersion('stripe');
    $this->patchJson(credentialsUrl('/stripe'), ['not_declared' => 'x'])->assertStatus(422);
    expect($resolver->credentialVersion('stripe'))->toBe($before);

    // The committed PATCH bumps the driver's rotation counter (multi-
    // tenancy §6.3 F1): the version-keyed caches rekey on the next
    // resolve — no explicit invalidation, no other driver touched.
    $this->patchJson(credentialsUrl('/stripe'), ['secret' => STRIPE_SECRET_PLAIN])->assertOk();
    expect($resolver->credentialVersion('stripe'))->toBeGreaterThan($before);
});

it('dispatches one audit event per committed mutation and never with values', function () {
    Event::fake([PaymentCredentialsUpdatedEvent::class]);

    $this->actingAs($this->superAdminUser());

    $this->patchJson(credentialsUrl('/stripe'), [
        'secret' => STRIPE_SECRET_PLAIN,
        'webhook_secret' => STRIPE_WEBHOOK_PLAIN,
    ])->assertOk();

    Event::assertDispatched(PaymentCredentialsUpdatedEvent::class, 1);
    Event::assertDispatched(function (PaymentCredentialsUpdatedEvent $event): bool {
        return $event->driver === 'stripe'
            && $event->keys === ['secret', 'webhook_secret']
            && $event->action === 'patch'
            // Values never enter the event — plaintext or ciphertext.
            && ! str_contains(json_encode($event), STRIPE_SECRET_PLAIN);
    });

    $this->deleteJson(credentialsUrl('/stripe/secret'))->assertOk();
    Event::assertDispatched(PaymentCredentialsUpdatedEvent::class, 2);
});

it('rejects empty strings, zeros, nulls, oversized and empty payloads', function (array $payload) {
    $this->actingAs($this->superAdminUser());

    $this->patchJson(credentialsUrl('/stripe'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['credentials']);

    expect(PaymentDriverCredential::count())->toBe(0);
})->with([
    'empty string' => [['secret' => '']],
    'zero value' => [['secret' => '0']],
    'null value' => [['secret' => null]],
    'oversized value' => [['secret' => str_repeat('a', 2049)]],
    'empty payload' => [[]],
]);

it('performs partial updates preserving omitted keys', function () {
    $this->actingAs($this->superAdminUser());

    $this->patchJson(credentialsUrl('/stripe'), [
        'secret' => STRIPE_SECRET_PLAIN,
        'webhook_secret' => STRIPE_WEBHOOK_PLAIN,
    ])->assertOk();

    // PATCH only secret: webhook_secret keeps its stored state.
    $this->patchJson(credentialsUrl('/stripe'), ['secret' => 'sk_rotated_value'])
        ->assertOk()
        ->assertJsonPath('data.keys.secret', 'stored')
        ->assertJsonPath('data.keys.webhook_secret', 'stored');

    expect(PaymentDriverCredential::query()->where('key', 'webhook_secret')->count())->toBe(1);
});

it('rejects unknown drivers, undeclared keys and non-configurable drivers without storing anything', function () {
    $this->actingAs($this->superAdminUser());

    // Unregistered driver.
    $this->patchJson(credentialsUrl('/notarealdriver'), ['secret' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'payment.credentials.driver_unknown');

    // Registered as a ConfigurableDriver that declares no keys (manual):
    // any submitted key is undeclared → request validation, never stored.
    $this->patchJson(credentialsUrl('/manual'), ['secret' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['credentials']);

    // Registered driver, undeclared key: rejected by request
    // validation (the action-level key guard is defense in depth).
    $this->patchJson(credentialsUrl('/stripe'), ['not_declared' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['credentials']);

    expect(PaymentDriverCredential::count())->toBe(0);
});

it('bumps the paypal credential version when paypal credentials rotate', function () {
    $this->actingAs($this->superAdminUser());

    // The version-keyed token cache rekeys on rotation (multi-tenancy
    // §6.3 F1, row 32): a rotated credential can never reuse another
    // tenant's/version's token, and the bump needs no explicit flush.
    $before = app(\Modules\Payment\Support\ResolvesPaymentCredentials::class)->credentialVersion('paypal');

    $this->patchJson(credentialsUrl('/paypal'), [
        'client_secret' => 'rotated_secret_value',
    ])->assertOk();

    expect(app(\Modules\Payment\Support\ResolvesPaymentCredentials::class)->credentialVersion('paypal'))->toBeGreaterThan($before);
});

it('treats empty environment values as unconfigured (legacy truthiness)', function () {
    seedEnvCredentials(['secret' => '', 'webhook_secret' => '0']);

    $this->actingAs($this->superAdminUser());

    // Review F8: the old manager rejected '' and '0' via `! config()`;
    // the resolver preserves that exactly.
    $this->getJson(credentialsUrl())
        ->assertOk()
        ->assertJsonPath('data.1.keys.secret', 'none')
        ->assertJsonPath('data.1.keys.webhook_secret', 'none')
        ->assertJsonPath('data.1.is_configured', false);
});

it('resolves env fallback from the immutable baseline, not the live config', function () {
    $this->actingAs($this->superAdminUser());

    // 1: baseline initializes (no stripe env anywhere).
    $this->getJson(credentialsUrl())->assertOk();

    // 2: a DB override lands and is applied to live config.
    $this->patchJson(credentialsUrl('/stripe'), ['secret' => STRIPE_SECRET_PLAIN])->assertOk();

    // 3: DELETE removes the override…
    $this->deleteJson(credentialsUrl('/stripe/secret'))->assertOk();

    // …and someone mutates the live config afterwards.
    config(['payment.stripe.secret' => 'sk_contaminated']);

    // 4: the resolver must fall back to the ORIGINAL baseline (no env),
    // never the contaminated live value (review F1b) — the baseline is an
    // immutable process snapshot, so the live-config mutation is invisible.
    $this->getJson(credentialsUrl())
        ->assertOk()
        ->assertJsonPath('data.1.keys.secret', 'none');

    expect(app(\Modules\Payment\Support\ResolvesPaymentCredentials::class)->credential('stripe', 'secret'))->toBeNull();
});

it('keeps the manager unconfigured matrix intact through the resolver', function () {
    seedEnvCredentials([
        'secret' => 'sk_env_fallback',
        'webhook_secret' => 'whsec_env_fallback',
    ]);

    // Regression guard for PaymentDriverManagerTest semantics: env-backed
    // credentials still resolve through the resolver.
    expect(app(PaymentDriverManager::class)->isConfigured('stripe'))->toBeTrue();
});
