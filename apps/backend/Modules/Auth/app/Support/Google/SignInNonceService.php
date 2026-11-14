<?php

namespace Modules\Auth\Support\Google;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Modules\Auth\Exceptions\AuthException;

/**
 * Single-use nonces binding a Google ID token to one sign-in attempt.
 *
 * The storefront fetches a nonce at button initialization, passes it to
 * Google Identity Services, and the issued ID token carries it as a claim;
 * the verifier consumes it before any account work. Storage contract:
 *
 * - Values are stored HASHED (SHA-256) — the cache never holds a usable nonce.
 * - The shared Redis store backs multi-instance deployments; a
 *   process-local fallback would silently break single-use and is refused.
 * - Consumption is ATOMIC (Lua get-and-delete): two concurrent requests
 *   using the same nonce yield exactly one winner.
 * - Consumed nonces are never restored, whatever happens later — a failed
 *   authentication still burns the nonce, forcing a fresh attempt.
 * - Every failure FAILS CLOSED: Redis unavailability rejects the attempt
 *   rather than degrading to a weaker store.
 */
class SignInNonceService
{
    /** Atomic get-and-delete: the single winner consumes, everyone else loses. */
    private const string CONSUME_SCRIPT = <<<'LUA'
        local value = redis.call('GET', KEYS[1])
        if value then
            redis.call('DEL', KEYS[1])
            return 1
        end
        return 0
    LUA;

    public function __construct(
        protected RedisFactory $redis,
    ) {}

    /**
     * Mint a fresh nonce and register its hash with a short TTL.
     */
    public function issue(): string
    {
        $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        try {
            $this->connection()->setex($this->key($nonce), $this->ttl(), 1);
        } catch (\Throwable $e) {
            throw $this->unavailable($e);
        }

        return $nonce;
    }

    /**
     * Atomically consume the presented nonce. Returns false when the nonce
     * was never issued, already consumed, or expired — the caller treats
     * all three as `nonce_invalid`. Consumed nonces are never restored.
     */
    public function consume(string $nonce): bool
    {
        try {
            // phpredis `eval` argument order (script, args, num_keys) via
            // the connection command passthrough — REDIS_CLIENT=phpredis
            // is pinned in the compose env.
            return (bool) $this->connection()->command('eval', [self::CONSUME_SCRIPT, [$this->key($nonce)], 1]);
        } catch (\Throwable $e) {
            throw $this->unavailable($e);
        }
    }

    private function connection(): \Illuminate\Redis\Connections\Connection
    {
        return $this->redis->connection();
    }

    private function key(string $nonce): string
    {
        return 'auth:google:nonce:'.hash('sha256', $nonce);
    }

    private function ttl(): int
    {
        return max(1, (int) config('auth.google.nonce_ttl', 300));
    }

    private function unavailable(\Throwable $e): AuthException
    {
        report($e);

        return AuthException::googleUnavailable();
    }
}
