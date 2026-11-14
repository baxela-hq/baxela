<?php

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Support\Google\GoogleEmailClass;
use Modules\Auth\Support\Google\GoogleIdTokenVerifier;
use Modules\Auth\Support\Google\SignInNonceService;

uses(RefreshDatabase::class);

const VERIFIER_CLIENT_ID = 'test-client-id.apps.googleusercontent.com';
const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

/**
 * A real RSA key pair published through Http::fake — the suite verifies
 * actual signatures and claims, never a faked verifier.
 */
function verifierKeypair(string $kid): array
{
    $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $details = openssl_pkey_get_details($res);

    $b64 = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    $privateKeyPem = '';
    openssl_pkey_export($res, $privateKeyPem);

    return [
        'kid' => $kid,
        'private' => $privateKeyPem,
        'jwk' => [
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $b64($details['rsa']['n']),
            'e' => $b64($details['rsa']['e']),
        ],
    ];
}

function publishJwks(array $keys): void
{
    Http::fake([
        JWKS_URL => Http::response(json_encode(['keys' => $keys]), 200, ['Content-Type' => 'application/json']),
    ]);
}

function verifier(): GoogleIdTokenVerifier
{
    return new GoogleIdTokenVerifier(app(SignInNonceService::class));
}

function verifierToken(array $claims, string $privateKey, string $kid): string
{
    $now = time();

    return JWT::encode(array_merge([
        'iss' => 'https://accounts.google.com',
        'aud' => VERIFIER_CLIENT_ID,
        'exp' => $now + 300,
        'iat' => $now,
        'nbf' => $now,
        'sub' => 'google-sub-abc',
        'email' => 'alice@gmail.com',
        'email_verified' => true,
        'nonce' => app(SignInNonceService::class)->issue(),
    ], $claims), $privateKey, 'RS256', $kid);
}

/** Token without the given top-level claim (crafted by re-encoding). */
function verifierTokenWithout(string $claim, string $privateKey, string $kid): string
{
    $parts = explode('.', verifierToken([], $privateKey, $kid));
    $payload = json_decode(JWT::urlsafeB64Decode($parts[1]), true);
    unset($payload[$claim]);

    return JWT::encode($payload, $privateKey, 'RS256', $kid);
}

beforeEach(function () {
    config(['auth.google.client_id' => VERIFIER_CLIENT_ID]);
});

it('verifies a valid gmail identity end to end', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    $identity = verifier()->verify(verifierToken([], $key['private'], 'key-1'), VERIFIER_CLIENT_ID);

    expect($identity->sub)->toBe('google-sub-abc')
        ->and($identity->email)->toBe('alice@gmail.com')
        ->and($identity->emailVerified)->toBeTrue()
        ->and($identity->hd)->toBeNull()
        ->and($identity->class)->toBe(GoogleEmailClass::GMAIL);
});

it('classifies googlemail as gmail and third-party domains as third party', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    $gmail = verifier()->verify(verifierToken(['email' => 'a@googlemail.com'], $key['private'], 'key-1'), VERIFIER_CLIENT_ID);
    $third = verifier()->verify(verifierToken(['email' => 'a@external.example', 'email_verified' => false], $key['private'], 'key-1'), VERIFIER_CLIENT_ID);

    expect($gmail->class)->toBe(GoogleEmailClass::GMAIL)
        ->and($third->class)->toBe(GoogleEmailClass::THIRD_PARTY)
        // The claim VALUE passes through untouched — policy decides later.
        ->and($third->emailVerified)->toBeFalse();
});

it('classifies a workspace identity only when hd matches the email domain', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    $workspace = verifier()->verify(verifierToken([
        'email' => 'bob@corp.example',
        'hd' => 'corp.example',
    ], $key['private'], 'key-1'), VERIFIER_CLIENT_ID);

    // Mixed case is normalized (the trailing-dot form is rejected below).
    $normalized = verifier()->verify(verifierToken([
        'email' => 'carol@corp.example',
        'hd' => 'CORP.Example',
    ], $key['private'], 'key-1'), VERIFIER_CLIENT_ID);

    expect($workspace->class)->toBe(GoogleEmailClass::WORKSPACE)
        ->and($workspace->hd)->toBe('corp.example')
        ->and($normalized->class)->toBe(GoogleEmailClass::WORKSPACE);
});

it('rejects an hd inconsistent with the email domain instead of downgrading', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    expect(fn () => verifier()->verify(verifierToken([
        'email' => 'bob@foo.example',
        'hd' => 'bar.example',
    ], $key['private'], 'key-1'), VERIFIER_CLIENT_ID))->toThrow(AuthException::class);
});

it('rejects a present-but-empty, non-string, or malformed hd', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    foreach (['', '   ', 123, 'foo.example.'] as $badHd) {
        expect(fn () => verifier()->verify(verifierToken([
            'email' => 'bob@external.example',
            'hd' => $badHd,
        ], $key['private'], 'key-1'), VERIFIER_CLIENT_ID))->toThrow(AuthException::class);
    }
});

it('rejects a bad signature', function () {
    $published = verifierKeypair('key-1');
    $forged = verifierKeypair('key-9');
    publishJwks([$published['jwk']]);

    expect(fn () => verifier()->verify(verifierToken([], $forged['private'], 'key-9'), VERIFIER_CLIENT_ID))
        ->toThrow(AuthException::class);
});

it('rejects a wrong issuer or wrong audience', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    foreach ([
        ['iss' => 'https://evil.example'],
        ['aud' => 'another-client-id'],
    ] as $claims) {
        expect(fn () => verifier()->verify(verifierToken($claims, $key['private'], 'key-1'), VERIFIER_CLIENT_ID))
            ->toThrow(AuthException::class);
    }
});

it('enforces the OIDC azp contract for multiple audiences', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    // Multi-audience + correct azp: accepted.
    $ok = verifier()->verify(verifierToken([
        'aud' => [VERIFIER_CLIENT_ID, 'other-client'],
        'azp' => VERIFIER_CLIENT_ID,
    ], $key['private'], 'key-1'), VERIFIER_CLIENT_ID);
    expect($ok->sub)->toBe('google-sub-abc');

    // Multi-audience with a wrong azp: rejected.
    expect(fn () => verifier()->verify(verifierToken([
        'aud' => [VERIFIER_CLIENT_ID, 'other-client'],
        'azp' => 'other-client',
    ], $key['private'], 'key-1'), VERIFIER_CLIENT_ID))->toThrow(AuthException::class);

    // Multi-audience missing azp: rejected (azp is REQUIRED).
    expect(fn () => verifier()->verify(verifierToken([
        'aud' => [VERIFIER_CLIENT_ID, 'other-client'],
    ], $key['private'], 'key-1'), VERIFIER_CLIENT_ID))->toThrow(AuthException::class);

    // Single audience with a wrong azp: rejected.
    expect(fn () => verifier()->verify(verifierToken([
        'azp' => 'other-client',
    ], $key['private'], 'key-1'), VERIFIER_CLIENT_ID))->toThrow(AuthException::class);
});

it('rejects expired and implausibly-future tokens', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    $now = time();

    expect(fn () => verifier()->verify(verifierToken(['exp' => $now - 120], $key['private'], 'key-1'), VERIFIER_CLIENT_ID))
        ->toThrow(AuthException::class);

    expect(fn () => verifier()->verify(verifierToken(['iat' => $now + 300], $key['private'], 'key-1'), VERIFIER_CLIENT_ID))
        ->toThrow(AuthException::class);
});

it('rejects missing or malformed sub/email/email_verified', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    foreach ([
        ['sub' => ''],
        ['email' => ''],
        ['email' => 'not-an-email'],
        ['email_verified' => null],
        ['email_verified' => 'true'],
        ['email_verified' => 1],
        'skip-email-verified',
    ] as $claims) {
        if ($claims === 'skip-email-verified') {
            expect(fn () => verifier()->verify(verifierTokenWithout('email_verified', $key['private'], 'key-1'), VERIFIER_CLIENT_ID))
                ->toThrow(AuthException::class);

            continue;
        }

        expect(fn () => verifier()->verify(verifierToken($claims, $key['private'], 'key-1'), VERIFIER_CLIENT_ID))
            ->toThrow(AuthException::class);
    }
});

it('requires the nonce claim and consumes exactly the token nonce', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    // Missing nonce claim → rejected even though the token is valid.
    expect(fn () => verifier()->verify(verifierTokenWithout('nonce', $key['private'], 'key-1'), VERIFIER_CLIENT_ID))
        ->toThrow(AuthException::class);

    // A nonce Google never issued → rejected.
    expect(fn () => verifier()->verify(verifierToken(['nonce' => 'never-issued-1234567890-1234567890'], $key['private'], 'key-1'), VERIFIER_CLIENT_ID))
        ->toThrow(AuthException::class);

    // Replay: the same token consumes its nonce exactly once.
    $token = verifierToken([], $key['private'], 'key-1');
    verifier()->verify($token, VERIFIER_CLIENT_ID);

    expect(fn () => verifier()->verify($token, VERIFIER_CLIENT_ID))
        ->toThrow(AuthException::class);
});

it('rejects a token whose kid is unknown even after a forced refresh', function () {
    $published = verifierKeypair('key-1');
    $unpublished = verifierKeypair('key-2');
    publishJwks([$published['jwk']]);

    expect(fn () => verifier()->verify(verifierToken([], $unpublished['private'], 'key-2'), VERIFIER_CLIENT_ID))
        ->toThrow(AuthException::class);
});

it('recovers from Google key rotation via a single-flight refresh', function () {
    $old = verifierKeypair('key-old');
    $new = verifierKeypair('key-new');

    // One fake with a mutable key set — re-calling Http::fake() would not
    // replace the earlier stub, so rotation is modeled by reassigning.
    $keys = [$old['jwk']];
    Http::fake([JWKS_URL => function () use (&$keys) {
        return Http::response(json_encode(['keys' => $keys]), 200, ['Content-Type' => 'application/json']);
    }]);

    // Token under the current key set — verified with the cached JWKS.
    verifier()->verify(verifierToken([], $old['private'], 'key-old'), VERIFIER_CLIENT_ID);

    // Google rotates: publish both keys, hand out a token signed by the
    // new key. The unknown kid triggers one refresh, then succeeds.
    $keys = [$old['jwk'], $new['jwk']];
    $identity = verifier()->verify(verifierToken(['sub' => 'rotated-sub'], $new['private'], 'key-new'), VERIFIER_CLIENT_ID);

    expect($identity->sub)->toBe('rotated-sub');

    // Exactly two JWKS fetches total: initial + one rotation refresh.
    Http::assertSentCount(2);
});

it('caches the JWKS so repeated verifications do not refetch', function () {
    $key = verifierKeypair('key-1');
    publishJwks([$key['jwk']]);

    verifier()->verify(verifierToken(['sub' => 'one'], $key['private'], 'key-1'), VERIFIER_CLIENT_ID);
    verifier()->verify(verifierToken(['sub' => 'two'], $key['private'], 'key-1'), VERIFIER_CLIENT_ID);

    Http::assertSentCount(1);
});

it('fails closed when the JWKS endpoint is unavailable', function () {
    Http::fake([JWKS_URL => Http::response('unavailable', 503)]);

    $key = verifierKeypair('key-1');

    expect(fn () => verifier()->verify(verifierToken([], $key['private'], 'key-1'), VERIFIER_CLIENT_ID))
        ->toThrow(AuthException::class);
});

it('rejects a non-RS256 algorithm before any signature work', function () {
    publishJwks([verifierKeypair('key-1')['jwk']]);

    // Hand-craft an alg:none token: header + payload, empty signature.
    $b64 = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    $header = $b64(['alg' => 'none', 'typ' => 'JWT']);
    $payload = $b64([
        'iss' => 'accounts.google.com',
        'aud' => VERIFIER_CLIENT_ID,
        'exp' => time() + 300,
        'sub' => 'x',
        'email' => 'a@gmail.com',
        'email_verified' => true,
        'nonce' => app(SignInNonceService::class)->issue(),
    ]);
    $forged = $header.'.'.$payload.'.';

    expect(fn () => verifier()->verify($forged, VERIFIER_CLIENT_ID))
        ->toThrow(AuthException::class);
});
