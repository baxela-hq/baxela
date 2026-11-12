<?php

namespace Modules\Auth\Support\TwoFactor;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\TwoFactorChallenge;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeSchema;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeTypeEnum;

/**
 * Challenge lifecycle (S.1 D4/D6/D17): issue, resolve under the
 * global user-first lock order, and consume atomically. The raw
 * token is 32 random bytes hex-encoded (64 chars) — only its
 * SHA-256 hash is stored as the PK. Challenges are self-cleaning
 * via the scheduled pruner.
 */
class TwoFactorChallengeService
{
    /** TTL in minutes for sign-in challenges. */
    public const int SIGN_IN_TTL_MINUTES = 5;

    /** TTL in minutes for enrollment challenges (more slack for QR scan). */
    public const int ENROLLMENT_TTL_MINUTES = 10;

    /**
     * Issue a challenge for a user. Follows D17 lock order: user
     * first, then challenge operations. Enforces active-challenge
     * limits (max 3 sign_in, max 1 enrollment per user). Returns the
     * raw token (shown to the client once — never stored).
     */
    public function issue(
        User $user,
        TwoFactorChallengeTypeEnum $type,
        array $context = [],
    ): string {
        return DB::transaction(function () use ($user, $type, $context): string {
            // D17: lock the user row first.
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            // Enforce active-challenge limits.
            $active = TwoFactorChallenge::query()
                ->where(TwoFactorChallengeSchema::USER_ID, $locked->id)
                ->ofType($type)
                ->active()
                ->count();

            if ($type === TwoFactorChallengeTypeEnum::ENROLLMENT && $active >= 1) {
                // D9: a new enrollment challenge invalidates the previous.
                TwoFactorChallenge::query()
                    ->where(TwoFactorChallengeSchema::USER_ID, $locked->id)
                    ->ofType($type)
                    ->active()
                    ->update([TwoFactorChallengeSchema::CONSUMED_AT => now()]);
            } elseif ($active >= TwoFactorChallenge::MAX_ACTIVE_SIGN_IN) {
                // D9: max 3 sign_in — invalidate the oldest.
                TwoFactorChallenge::query()
                    ->where(TwoFactorChallengeSchema::USER_ID, $locked->id)
                    ->ofType($type)
                    ->active()
                    ->oldest()
                    ->limit(1)
                    ->update([TwoFactorChallengeSchema::CONSUMED_AT => now()]);
            }

            // Generate the raw token and its hash.
            $rawToken = bin2hex(random_bytes(32));
            $hash = hash('sha256', $rawToken);

            $ttl = $type === TwoFactorChallengeTypeEnum::ENROLLMENT
                ? self::ENROLLMENT_TTL_MINUTES
                : self::SIGN_IN_TTL_MINUTES;

            TwoFactorChallenge::query()->create([
                TwoFactorChallengeSchema::TOKEN_HASH => $hash,
                TwoFactorChallengeSchema::USER_ID => $locked->id,
                TwoFactorChallengeSchema::TYPE => $type->value,
                TwoFactorChallengeSchema::CONTEXT => $context,
                TwoFactorChallengeSchema::EXPIRES_AT => now()->addMinutes($ttl),
            ]);

            return $rawToken;
        });
    }

    /**
     * Resolve a challenge by its raw token for a specific user — the
     * D17 lookup step (no lock; the caller locks the user and
     * challenge rows in their transaction). Returns null when the
     * token hash is not found for this user.
     */
    public function lookup(string $rawToken, int $userId, TwoFactorChallengeTypeEnum $expectedType): ?TwoFactorChallenge
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }

        $hash = hash('sha256', $rawToken);

        $challenge = TwoFactorChallenge::query()
            ->where(TwoFactorChallengeSchema::TOKEN_HASH, $hash)
            ->where(TwoFactorChallengeSchema::USER_ID, $userId)
            ->first();

        if ($challenge === null) {
            return null;
        }

        // D9: challenge-type isolation.
        if ($challenge->{TwoFactorChallengeSchema::TYPE} !== $expectedType->value) {
            return null;
        }

        return $challenge;
    }

    /**
     * Find the user_id for a challenge token WITHOUT locking (the D17
     * pre-lock lookup). Returns null when the token doesn't exist.
     */
    public function userIdFor(string $rawToken): ?int
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }

        $hash = hash('sha256', $rawToken);

        return TwoFactorChallenge::query()
            ->where(TwoFactorChallengeSchema::TOKEN_HASH, $hash)
            ->value(TwoFactorChallengeSchema::USER_ID);
    }

    /**
     * Lock and revalidate a challenge inside the caller's transaction.
     * The caller must have already locked the user row (D17 order).
     * Returns the locked challenge row or null on any failure.
     */
    public function lockAndValidate(
        int $userId,
        string $rawToken,
        TwoFactorChallengeTypeEnum $expectedType,
    ): ?TwoFactorChallenge {
        if (! preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }

        $hash = hash('sha256', $rawToken);

        $challenge = TwoFactorChallenge::query()
            ->where(TwoFactorChallengeSchema::TOKEN_HASH, $hash)
            ->where(TwoFactorChallengeSchema::USER_ID, $userId)
            ->lockForUpdate()
            ->first();

        if ($challenge === null) {
            return null;
        }

        if ($challenge->{TwoFactorChallengeSchema::TYPE} !== $expectedType->value) {
            return null;
        }

        if ($challenge->isConsumed() || $challenge->isExpired() || $challenge->isExhausted()) {
            return null;
        }

        return $challenge;
    }

    /**
     * Consume a challenge (mark consumed_at + null context). MUST be
     * called inside the caller's transaction, after the code was
     * verified.
     */
    public function consume(TwoFactorChallenge $challenge): void
    {
        $challenge->update([
            TwoFactorChallengeSchema::CONSUMED_AT => now(),
            TwoFactorChallengeSchema::CONTEXT => null,
        ]);
    }

    /**
     * Record a failed attempt on a challenge. MUST be inside the
     * caller's transaction (the row is already locked).
     */
    public function recordFailure(TwoFactorChallenge $challenge): void
    {
        $challenge->increment(TwoFactorChallengeSchema::FAILED_ATTEMPTS);
    }

    /**
     * Invalidate all active challenges for a user (D9 triggers).
     * MUST be inside the caller's transaction with the user locked.
     */
    public function invalidateAllForUser(int $userId): void
    {
        TwoFactorChallenge::query()
            ->where(TwoFactorChallengeSchema::USER_ID, $userId)
            ->whereNull(TwoFactorChallengeSchema::CONSUMED_AT)
            ->update([
                TwoFactorChallengeSchema::CONSUMED_AT => now(),
                TwoFactorChallengeSchema::CONTEXT => null,
            ]);
    }
}
