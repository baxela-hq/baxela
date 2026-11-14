<?php

namespace Modules\Auth\Actions\Public\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Exceptions\AccountNotActivatedException;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Token\PersonalAccessTokenSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Schemas\UserDevice\UserDeviceSchema;
use Modules\Auth\Support\SignInLockout\SignInLockoutService;
use Modules\Core\Contracts\Events\Auth\NewDeviceSignInEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Core\Utils\Locale;
use RuntimeException;
use Throwable;

/**
 * The authoritative successful-authentication sequence (S.1 D15),
 * shared by both the 2FA and non-2FA sign-in paths. Split into two
 * halves so the 2FA flow can call issueToken() inside its transaction
 * (under the user-row lock — a concurrent disable cannot interleave)
 * and defer dispatchEvents() until after commit.
 *
 * S.4: issueToken() is also the lockout boundary — the lockout row is
 * the shared serialization point between the failed-password path and
 * token issuance, so a locked account can never complete through ANY
 * completion path (D2 rev 7). It performs no counter mutation: the
 * counter is cleared by SignInAction when the password verifies (D2f).
 */
class CompleteAuthenticationAction
{
    public function __construct(
        protected SignInLockoutService $lockout,
    ) {}

    /**
     * DB-only work: re-check is_active under the user-row lock, check the
     * lockout under the lockout-row lock, register the device, create the
     * new token. Safe inside a transaction — no external side effects.
     *
     * @param  array{device_name?: string, remember?: bool, cart_token?: string|null}  $context  the sign-in context (from the challenge or the original request; the challenge context may be empty)
     * @return array{user: User, token: string, device_is_new: bool}
     *
     * @throws AuthException AUTH_SIGN_IN_LOCKED when the account is locked
     * @throws AccountNotActivatedException
     */
    public function issueToken(User $user, array $context): array
    {
        $emailHash = SignInLockoutService::emailKey($user->{UserSchema::EMAIL});

        return DB::transaction(function () use ($user, $context, $emailHash) {
            // Re-check immediately before issuing under the row lock — a
            // deactivated account must never receive a token, even
            // mid-challenge (D15). In the 2FA paths the caller already
            // holds this row lock; the re-lock is a no-op there.
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $user->{UserSchema::IS_ACTIVE}) {
                throw new AccountNotActivatedException;
            }

            // S.4 boundary: bootstrap the lockout row and check the lock
            // while HOLDING it — establishing a lock and issuing a token
            // serialize against the same row lock (D2 rev 7). Throwing
            // rolls the caller's transaction back cleanly: the 2FA
            // challenge is NOT consumed and remains retryable after the
            // lock expires.
            $lockRow = $this->lockout->bootstrapForUpdate($emailHash);

            if ($this->lockout->isLockedRow($lockRow)) {
                $this->lockout->throwLocked($emailHash);
            }

            $deviceName = $context['device_name'] ?? 'unknown-device';
            $deviceIsNew = $this->registerDevice($user, $deviceName);

            // Replace only this device's previous token (one token per device).
            $user->tokens()
                ->where(PersonalAccessTokenSchema::NAME, $deviceName)
                ->delete();

            $remembered = $context['remember'] ?? true;
            $ttlDays = (int) config('auth.token_ttl.'.($remembered ? 'days' : 'short_days'));

            $token = $user->createToken(
                $deviceName,
                ['*'],
                now()->addDays($ttlDays),
            )->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'device_is_new' => $deviceIsNew,
            ];
        });
    }

    /**
     * External side effects: fire the UserSignedInEvent with the cart-merge
     * token, plus the new-device event when this is the first sign-in from
     * the device (S.4). MUST be called after the caller's transaction
     * commits — events must never run inside a rollback-able scope.
     *
     * Unchanged for the password and 2FA callers: exceptions from dispatch
     * propagate exactly as before. The Google sign-in path uses
     * dispatchEventsBestEffort() instead.
     *
     * @param  array{device_name?: string, remember?: bool, cart_token?: string|null}  $context
     */
    public function dispatchEvents(User $user, array $context, bool $deviceIsNew = false): void
    {
        event($this->signInEvent($user, $context));

        if ($deviceIsNew) {
            event($this->newDeviceSignInEvent($user, $context));
        }
    }

    /**
     * Google-only variant of dispatchEvents(): identical events, payloads,
     * ordering and `deviceIsNew` condition, but each attempt is wrapped in
     * its own try/catch so one failure never suppresses the other, and a
     * dispatch failure after a successful commit never turns the committed
     * authentication into an HTTP failure. Failures are logged with
     * diagnostic context, never credentials or tokens.
     *
     * @param  array{device_name?: string, remember?: bool, cart_token?: string|null}  $context
     */
    public function dispatchEventsBestEffort(User $user, array $context, bool $deviceIsNew = false): void
    {
        try {
            event($this->signInEvent($user, $context));
        } catch (Throwable $e) {
            $this->logDispatchFailure('user_signed_in', $user, $e);
        }

        if ($deviceIsNew) {
            try {
                event($this->newDeviceSignInEvent($user, $context));
            } catch (Throwable $e) {
                $this->logDispatchFailure('new_device_sign_in', $user, $e);
            }
        }
    }

    /**
     * @param  array{device_name?: string, remember?: bool, cart_token?: string|null}  $context
     */
    private function signInEvent(User $user, array $context): UserSignedInEvent
    {
        return new UserSignedInEvent(
            $user->{UserSchema::ID},
            $user->{UserSchema::EMAIL},
            now()->toDateTimeString(),
            $context['cart_token'] ?? null,
        );
    }

    /**
     * @param  array{device_name?: string, remember?: bool, cart_token?: string|null}  $context
     */
    private function newDeviceSignInEvent(User $user, array $context): NewDeviceSignInEvent
    {
        return new NewDeviceSignInEvent(
            $user->{UserSchema::ID},
            $user->{UserSchema::EMAIL},
            $context['device_name'] ?? 'unknown-device',
            now()->toDateTimeString(),
            // Locale captured at dispatch time so the queued listener
            // renders in the recipient's language (D8, house pattern).
            Locale::fromRequest(),
        );
    }

    /** Diagnostic context for a swallowed post-commit dispatch failure. */
    private function logDispatchFailure(string $event, User $user, Throwable $e): void
    {
        Log::warning('Google sign-in: post-commit event dispatch failed', [
            'event' => $event,
            'stage' => 'after_commit_dispatch',
            'user_id' => $user->getKey(),
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }

    /**
     * Persistent device memory (S.4 D5): insert-if-absent decided by the
     * affected-row count, so the unique (user, device) constraint
     * arbitrates concurrent first registration without any exception
     * entering the surrounding transaction (F3). A zero count means the
     * row already exists — OR that a non-duplicate error was suppressed;
     * the follow-up existence check surfaces the latter instead of
     * misreading it as "already seen" (D2d).
     */
    private function registerDevice(User $user, string $deviceName): bool
    {
        $now = now();

        $inserted = DB::table(UserDeviceSchema::TABLE)->insertOrIgnore([
            UserDeviceSchema::USER_ID => $user->getKey(),
            UserDeviceSchema::DEVICE_NAME => $deviceName,
            UserDeviceSchema::FIRST_SEEN_AT => $now,
            UserDeviceSchema::LAST_SEEN_AT => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted > 0) {
            return true;
        }

        $exists = DB::table(UserDeviceSchema::TABLE)
            ->where(UserDeviceSchema::USER_ID, $user->getKey())
            ->where(UserDeviceSchema::DEVICE_NAME, $deviceName)
            ->exists();

        if (! $exists) {
            throw new RuntimeException('User device registration was suppressed unexpectedly.');
        }

        DB::table(UserDeviceSchema::TABLE)
            ->where(UserDeviceSchema::USER_ID, $user->getKey())
            ->where(UserDeviceSchema::DEVICE_NAME, $deviceName)
            ->update([
                UserDeviceSchema::LAST_SEEN_AT => $now,
                'updated_at' => $now,
            ]);

        return false;
    }
}
