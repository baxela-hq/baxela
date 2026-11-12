<?php

namespace Modules\Auth\Support\SignInLockout;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Schemas\SignInLockout\SignInLockoutSchema;
use RuntimeException;

/**
 * S.4 account lockout. State lives in the auth_sign_in_lockouts row keyed
 * by the SHA-256 of the canonical email — shared by the failed-password
 * path (recordFailure/clearAttempts) and the token-issuance boundary
 * (bootstrapForUpdate), which serialize against the SAME row lock, so
 * establishing a lock can never interleave with authorizing a token.
 * The per-email cache gate (30s lease) only serializes credential
 * verification for throughput; the security invariant does NOT depend on
 * its lease (D1-rev4).
 *
 * Lock-order invariant (F12): every method here is called while holding
 * the email cache gate, and no boundary path calls this service while
 * holding it — the lockout-row lock is always the LAST lock acquired.
 */
final class SignInLockoutService
{
    /** Cache gate lease — >=30x the expected critical-section duration. */
    public const int GATE_LEASE_SECONDS = 30;

    /** Bounded acquisition; expiry is fail-closed (503), never a 429. */
    public const int GATE_ACQUIRE_SECONDS = 3;

    /** Rows past both their lock and this retention are inert (prune). */
    public const int PRUNE_AFTER_DAYS = 7;

    public static function canonicalEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** SHA-256 of the canonical email — raw addresses never hit keys/rows. */
    public static function emailKey(string $email): string
    {
        return hash('sha256', self::canonicalEmail($email));
    }

    /**
     * Serialize the attempt phase: pre-check + credential verification +
     * counter writes. The callback must not acquire the user-row lock
     * (lock-order invariant — see the class docblock).
     *
     * @param  Closure(): mixed  $callback
     * @return mixed the callback's return value
     */
    public function gate(string $emailHash, Closure $callback): mixed
    {
        $lock = Cache::lock('sign-in-gate:'.$emailHash, self::GATE_LEASE_SECONDS);

        try {
            // block() releases the lock on return AND on throw (the
            // callback runs inside try/finally).
            return $lock->block(self::GATE_ACQUIRE_SECONDS, $callback);
        } catch (LockTimeoutException) {
            // Fail-closed contention: never a 429 — that would lie about
            // lock state. 503 reports to Sentry (contention signal).
            throw AuthException::signInBusy();
        }
    }

    /**
     * Attempt-phase pre-check: plain read, no row bootstrap, no lock —
     * the authoritative check happens under the row lock elsewhere.
     *
     * @throws AuthException AUTH_SIGN_IN_LOCKED when actively locked
     */
    public function assertNotLocked(string $emailHash, ?ConnectionInterface $connection = null): void
    {
        $row = $this->rowFor($emailHash, $connection);

        if ($this->isLockedRow($row)) {
            $this->throwLocked($emailHash, $connection);
        }
    }

    /**
     * Record a failed password attempt. Zero writes under an active lock
     * (D2e): rejected attempts must never extend the lock — and never
     * touch last_failed_at, so the counter is stale by the moment the
     * lock expires (D2i) and the account gets fresh attempts.
     */
    public function recordFailure(string $emailHash, ?ConnectionInterface $connection = null): FailureOutcome
    {
        $connection ??= DB::connection();

        return $connection->transaction(function () use ($emailHash, $connection): FailureOutcome {
            $row = $this->bootstrapForUpdate($emailHash, $connection);
            $now = now();

            if ($this->isLockedRow($row)) {
                return FailureOutcome::LockedWhileInFlight;
            }

            $maxAttempts = max(1, (int) config('auth.lockout.max_attempts'));
            $windowMinutes = max(1, (int) config('auth.lockout.lockout_minutes'));
            $lastFailedAt = $row->{SignInLockoutSchema::LAST_FAILED_AT};

            // Inactivity-reset counter (D2c/D2i): the window IS the
            // lockout duration — not a rolling window; failures spaced
            // closer than the window accumulate until lockout.
            $stale = $lastFailedAt === null
                || Carbon::parse($lastFailedAt)->lt($now->copy()->subMinutes($windowMinutes));

            $attempts = $stale
                ? 1
                : (int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS} + 1;

            $update = [
                SignInLockoutSchema::FAILED_ATTEMPTS => $attempts,
                SignInLockoutSchema::LAST_FAILED_AT => $now,
                'updated_at' => $now,
            ];

            if ($attempts >= $maxAttempts) {
                $update[SignInLockoutSchema::LOCKED_AT] = $now;
                $update[SignInLockoutSchema::LOCKED_UNTIL] = $now->copy()->addMinutes($windowMinutes);
            }

            $this->table($connection)
                ->where(SignInLockoutSchema::EMAIL_HASH, $emailHash)
                ->update($update);

            return FailureOutcome::Recorded;
        });
    }

    /**
     * Clear the failure counter after successful password verification
     * (D2f). A guard no-op under an active lock — the caller stops the
     * flow on that outcome (D2h): a valid password is not permission to
     * continue.
     */
    public function clearAttempts(string $emailHash, ?ConnectionInterface $connection = null): FailureOutcome
    {
        $connection ??= DB::connection();

        return $connection->transaction(function () use ($emailHash, $connection): FailureOutcome {
            $row = $this->bootstrapForUpdate($emailHash, $connection);

            if ($this->isLockedRow($row)) {
                return FailureOutcome::LockedWhileInFlight;
            }

            $this->table($connection)
                ->where(SignInLockoutSchema::EMAIL_HASH, $emailHash)
                ->update([
                    SignInLockoutSchema::FAILED_ATTEMPTS => 0,
                    SignInLockoutSchema::LAST_FAILED_AT => null,
                    'updated_at' => now(),
                ]);

            return FailureOutcome::Cleared;
        });
    }

    /**
     * D2a: create-if-absent FIRST (the unique constraint arbitrates
     * concurrent creation), then lock the now-existing row. A
     * SELECT ... FOR UPDATE cannot lock a missing row (InnoDB gap locks
     * are not mutually exclusive) — never lock before the row exists.
     * upsert() compiles to ON DUPLICATE KEY UPDATE / ON CONFLICT DO
     * UPDATE, so only a duplicate-key conflict is absorbed — unlike
     * INSERT IGNORE, any unexpected error propagates.
     *
     * Must be called inside a transaction on $connection.
     */
    public function bootstrapForUpdate(string $emailHash, ?ConnectionInterface $connection = null): object
    {
        $connection ??= DB::connection();
        $now = now();

        $this->table($connection)->upsert(
            [[
                SignInLockoutSchema::EMAIL_HASH => $emailHash,
                SignInLockoutSchema::FAILED_ATTEMPTS => 0,
                SignInLockoutSchema::LAST_FAILED_AT => null,
                SignInLockoutSchema::LOCKED_UNTIL => null,
                SignInLockoutSchema::LOCKED_AT => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            [SignInLockoutSchema::EMAIL_HASH],
            [SignInLockoutSchema::EMAIL_HASH],
        );

        $row = $this->table($connection)
            ->where(SignInLockoutSchema::EMAIL_HASH, $emailHash)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            // The upsert guarantees existence — a miss means the insert
            // was silently swallowed, which must surface, not pass.
            throw new RuntimeException('Sign-in lockout bootstrap did not yield a row.');
        }

        return $row;
    }

    public function isLockedRow(?object $row): bool
    {
        if ($row === null || $row->{SignInLockoutSchema::LOCKED_UNTIL} === null) {
            return false;
        }

        return Carbon::parse($row->{SignInLockoutSchema::LOCKED_UNTIL})->isFuture();
    }

    /**
     * @return array{0: int, 1: int} seconds and minutes until unlock (>= 1)
     */
    public function retryAfter(string $emailHash, ?ConnectionInterface $connection = null): array
    {
        $row = $this->rowFor($emailHash, $connection);

        $seconds = 1;

        if ($row !== null && $row->{SignInLockoutSchema::LOCKED_UNTIL} !== null) {
            // Timestamp arithmetic — Carbon 3's diffInSeconds is signed and
            // a future unlock would come back negative.
            $remaining = Carbon::parse($row->{SignInLockoutSchema::LOCKED_UNTIL})->getTimestamp() - now()->getTimestamp();
            $seconds = max(1, (int) ceil($remaining));
        }

        return [$seconds, max(1, (int) ceil($seconds / 60))];
    }

    /**
     * @throws AuthException AUTH_SIGN_IN_LOCKED always
     */
    public function throwLocked(string $emailHash, ?ConnectionInterface $connection = null): never
    {
        [$seconds, $minutes] = $this->retryAfter($emailHash, $connection);

        throw AuthException::signInAccountLocked($seconds, $minutes);
    }

    private function rowFor(string $emailHash, ?ConnectionInterface $connection = null): ?object
    {
        $connection ??= DB::connection();

        return $this->table($connection)
            ->where(SignInLockoutSchema::EMAIL_HASH, $emailHash)
            ->first();
    }

    private function table(ConnectionInterface $connection): \Illuminate\Database\Query\Builder
    {
        return $connection->table(SignInLockoutSchema::TABLE);
    }
}
