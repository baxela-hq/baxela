<?php

namespace Modules\Auth\Support\Google;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Exceptions\AuthException;
use Throwable;

/**
 * Strict server-side verification of Google ID tokens (OpenID Connect),
 * per docs/plans/google-sign-in.md invariant 3 and the verifier contract:
 *
 * ① parse the token untrusted ② require RS256 + locate the JWKS key
 * (cached ~6 h; unknown `kid` → single-flight refresh) ③ verify the
 * signature ④ validate iss/aud/azp/exp/iat/sub/email and require
 * `email_verified` to be a genuine boolean ⑤ require a non-empty `nonce`
 * claim ⑥ atomically consume that exact claim ⑦ only then does account
 * resolution begin.
 *
 * The claim's VALUE policy (who may activate instantly) is applied later,
 * at account resolution — the verifier only guarantees the claim is a
 * real boolean. No unverified decoding fallback exists; every failure is
 * a typed `auth.google.*` exception, and the raw token is never logged.
 */
class GoogleIdTokenVerifier
{
    private const string JWKS_CACHE_KEY = 'auth:google:jwks';

    private const string JWKS_REFRESH_LOCK = 'auth:google:jwks:refresh';

    /** One hour of clock skew tolerance for exp/iat/nbf (OIDC guidance: a few minutes is generous). */
    private const int LEEWAY_SECONDS = 60;

    /** Response size cap for the JWKS endpoint — Google's key set is a few KB. */
    private const int JWKS_MAX_BYTES = 65536;

    private const array ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    private const array GOOGLE_HOSTED_DOMAINS = ['gmail.com', 'googlemail.com'];

    public function __construct(
        protected SignInNonceService $nonces,
    ) {}

    /**
     * @throws AuthException on every failure path (invalid token, nonce,
     *                    configuration, or unavailable dependencies)
     */
    public function verify(string $credential, string $clientId): GoogleIdentity
    {
        $clientId = trim((string) $clientId);

        if ($clientId === '') {
            // Misconfiguration is not a client error, but never proceed
            // without an audience to enforce — fail closed either way.
            throw AuthException::googleUnavailable();
        }

        // ① Untrusted parse — structure only, nothing is trusted yet.
        $parts = explode('.', $credential);

        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
            throw AuthException::googleInvalidToken();
        }

        $header = $this->decodeHeaderSegment($parts[0]);

        // ② Algorithm pinning + key lookup. RS256 only — Google never
        // signs ID tokens with anything else, and accepting others would
        // widen the attack surface for no benefit.
        if (($header->alg ?? null) !== 'RS256') {
            throw AuthException::googleInvalidToken();
        }

        $kid = $header->kid ?? null;

        if (! is_string($kid) || $kid === '') {
            throw AuthException::googleInvalidToken();
        }

        // ③ Signature verification (exp/nbf/iat are also enforced by the
        // library with leeway). Key rotation: a `kid` missing from the
        // cached set triggers one single-flight refresh before rejection.
        $keys = $this->resolveKeys($kid);
        JWT::$leeway = self::LEEWAY_SECONDS;

        try {
            $claims = JWT::decode($credential, $keys);
        } catch (SignatureInvalidException|BeforeValidException|ExpiredException|\InvalidArgumentException) {
            throw AuthException::googleInvalidToken();
        } catch (Throwable $e) {
            report($e);

            throw AuthException::googleInvalidToken();
        }

        // ④ Claim validation — structure first; the email-verification
        // VALUE is policy, resolved later at account resolution.
        if (in_array($claims->iss ?? null, self::ISSUERS, true) === false) {
            throw AuthException::googleInvalidToken();
        }

        if (! $this->audienceMatches($claims, $clientId)) {
            throw AuthException::googleInvalidToken();
        }

        $sub = $claims->sub ?? null;

        if (! is_string($sub) || $sub === '' || strlen($sub) > 255) {
            throw AuthException::googleInvalidToken();
        }

        $email = $claims->email ?? null;

        if (! is_string($email) || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw AuthException::googleInvalidToken();
        }

        // Implausibly future-issued tokens are rejected explicitly — the
        // JWT library enforces exp/nbf but not a future `iat`.
        $iat = $claims->iat ?? null;

        if ($iat !== null && (! is_int($iat) || $iat > time() + self::LEEWAY_SECONDS)) {
            throw AuthException::googleInvalidToken();
        }

        $emailVerified = $claims->email_verified ?? null;

        if (! is_bool($emailVerified)) {
            // Missing, null, 0/1, "true" — all malformed for OIDC.
            throw AuthException::googleInvalidToken();
        }

        $name = isset($claims->name) && is_string($claims->name) && $claims->name !== '' ? $claims->name : null;
        $picture = isset($claims->picture) && is_string($claims->picture) && $claims->picture !== '' ? $claims->picture : null;

        // ⑤ The nonce must be present in the SIGNATURE-VERIFIED token — a
        // request-body nonce is never accepted as a substitute.
        $nonceClaim = $claims->nonce ?? null;

        if (! is_string($nonceClaim) || $nonceClaim === '') {
            throw AuthException::googleNonceInvalid();
        }

        // ⑥ Atomic single-use consumption — unreachable until the token is
        // cryptographically valid, so invalid signatures can never burn
        // arbitrary nonces. Consumed nonces are never restored.
        if ($this->nonces->consume($nonceClaim) === false) {
            throw AuthException::googleNonceInvalid();
        }

        // ⑦ Classification — the action applies the account policy.
        $hd = $this->normalizedHostedDomain($claims->hd ?? null);

        return new GoogleIdentity(
            sub: $sub,
            email: $email,
            emailVerified: $emailVerified,
            hd: $hd,
            class: $this->classify($email, $hd),
            name: $name,
            picture: $picture,
        );
    }

    /**
     * OIDC aud/azp contract: single audience must equal the client id;
     * multiple audiences REQUIRE `azp` equal to the client id; `azp` when
     * present must always equal the client id.
     */
    private function audienceMatches(object $claims, string $clientId): bool
    {
        $aud = $claims->aud ?? null;

        if (is_string($aud)) {
            $audiences = [$aud];
        } elseif (is_array($aud)) {
            $audiences = $aud;
        } else {
            return false;
        }

        if (count($audiences) === 0 || in_array($clientId, $audiences, true) === false) {
            return false;
        }

        $azp = $claims->azp ?? null;

        if (count($audiences) > 1 && (! is_string($azp) || $azp !== $clientId)) {
            // `azp` is REQUIRED for multiple audiences.
            return false;
        }

        return $azp === null || $azp === $clientId;
    }

    /**
     * Normalizes the hosted-domain claim. Absent → null (third-party
     * candidate). Present but empty, non-string, or not a plausible
     * lowercase hostname (trailing dots, spaces, unicode → reject) is a
     * malformed claim and throws — it must never enter the third-party
     * path (invariant 3).
     *
     * @throws AuthException
     */
    private function normalizedHostedDomain(mixed $hd): ?string
    {
        if ($hd === null) {
            return null;
        }

        if (! is_string($hd)) {
            throw AuthException::googleInvalidToken();
        }

        $normalized = strtolower(trim($hd));

        if ($normalized === '' || str_ends_with($normalized, '.') || str_contains($normalized, ' ')
            || preg_match('/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/', $normalized) !== 1) {
            throw AuthException::googleInvalidToken();
        }

        return $normalized;
    }

    /**
     * @throws AuthException for a `hd` inconsistent with the email domain
     */
    private function classify(string $email, ?string $hd): GoogleEmailClass
    {
        $emailDomain = strtolower(substr($email, strrpos($email, '@') + 1));

        if ($hd !== null) {
            // A hosted domain that disagrees with the email domain means we
            // cannot tell which identity Google is vouching for — reject.
            if ($emailDomain !== $hd) {
                throw AuthException::googleInvalidToken();
            }

            return GoogleEmailClass::WORKSPACE;
        }

        if (in_array($emailDomain, self::GOOGLE_HOSTED_DOMAINS, true)) {
            return GoogleEmailClass::GMAIL;
        }

        return GoogleEmailClass::THIRD_PARTY;
    }

    /**
     * JWKS lookup with unknown-`kid` recovery: a miss against the cached
     * set triggers ONE single-flight refresh (a `Cache::lock` prevents a
     * refresh storm when many requests hit rotation at once — a waiter
     * whose lock times out re-checks the cache and fails closed instead of
     * fetching on its own), then a retry; a key still missing afterwards
     * is a hard rejection.
     *
     * @return array<string, \Firebase\JWT\Key>
     *
     * @throws AuthException when the keys cannot be resolved
     */
    private function resolveKeys(string $kid): array
    {
        $jwks = $this->cachedJwks();

        if ($jwks !== null && isset($jwks['keys']) && is_array($jwks['keys'])) {
            $keys = JWK::parseKeySet($jwks, 'RS256');

            if (array_key_exists($kid, $keys)) {
                return $keys;
            }
        }

        // Unknown `kid` (or no cache) — refresh once, single-flight.
        $lock = Cache::lock(self::JWKS_REFRESH_LOCK, 5);
        $acquired = false;

        try {
            $lock->block(3);
            $acquired = true;
        } catch (LockTimeoutException) {
            // Another request is already refreshing (bounded wait, never
            // unbounded): re-check the cache it may have just filled.
            $jwks = $this->cachedJwks();

            if ($jwks !== null && isset($jwks['keys']) && is_array($jwks['keys'])) {
                $keys = JWK::parseKeySet($jwks, 'RS256');

                if (array_key_exists($kid, $keys)) {
                    return $keys;
                }

                throw AuthException::googleInvalidToken();
            }

            throw AuthException::googleUnavailable();
        }

        try {
            $jwks = $this->fetchJwks();

            if ($jwks === null) {
                throw AuthException::googleUnavailable();
            }

            $keys = JWK::parseKeySet($jwks, 'RS256');

            if (array_key_exists($kid, $keys)) {
                return $keys;
            }

            // Key still unknown after a forced refresh — token does not
            // belong to this Google project's current key set.
            throw AuthException::googleInvalidToken();
        } finally {
            // The lock outlives the fetch otherwise (its TTL is longer than
            // the wait), starving the next rotation recovery.
            $lock->release();
        }
    }

    /** @return array<string, mixed>|null */
    private function cachedJwks(): ?array
    {
        $jwks = Cache::get(self::JWKS_CACHE_KEY);

        return is_array($jwks) ? $jwks : null;
    }

    /**
     * Fetch Google's public key set over HTTPS with bounded timeouts and a
     * response size cap, then cache it (~6 h).
     */
    /** @return array<string, mixed>|null */
    private function fetchJwks(): ?array
    {
        $response = Http::timeout(5)
            ->connectTimeout(3)
            ->get('https://www.googleapis.com/oauth2/v3/certs');

        if ($response->failed()) {
            return null;
        }

        $body = $response->body();

        if (strlen($body) > self::JWKS_MAX_BYTES) {
            report(new \UnexpectedValueException('Google JWKS response exceeded the size cap.'));

            return null;
        }

        $jwks = json_decode($body, true);

        if (! is_array($jwks) || ! isset($jwks['keys']) || ! is_array($jwks['keys'])) {
            report(new \UnexpectedValueException('Google JWKS response was not a usable key set.'));

            return null;
        }

        Cache::put(self::JWKS_CACHE_KEY, $jwks, now()->addHours(6));

        return $jwks;
    }

    /**
     * Parses ONLY the JWT header (untrusted): algorithm pinning + key id
     * come from here; everything else is validated from the verified
     * payload, never from this parse.
     *
     * @throws AuthException on a structurally invalid segment
     */
    private function decodeHeaderSegment(string $segment): object
    {
        $json = JWT::urlsafeB64Decode($segment);
        $decoded = JWT::jsonDecode($json);

        if (! is_object($decoded)) {
            throw AuthException::googleInvalidToken();
        }

        return $decoded;
    }
}
