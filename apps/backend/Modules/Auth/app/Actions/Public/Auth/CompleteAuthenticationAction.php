<?php

namespace Modules\Auth\Actions\Public\Auth;

use Modules\Auth\Exceptions\AccountNotActivatedException;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Token\PersonalAccessTokenSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;

/**
 * The authoritative successful-authentication sequence (S.1 D15),
 * shared by both the 2FA and non-2FA sign-in paths. Split into two
 * halves so the 2FA flow can call issueToken() inside its transaction
 * (under the user-row lock — a concurrent disable cannot interleave)
 * and defer dispatchEvents() until after commit.
 */
class CompleteAuthenticationAction
{
    /**
     * DB-only work: re-check is_active, replace the previous token
     * for this device, create the new token. Safe inside a
     * transaction — no external side effects.
     *
     * @param  array{device_name: string, remember: bool, cart_token: ?string}  $context  the sign-in context (from the challenge or the original request)
     * @return array{user: User, token: string}
     */
    public function issueToken(User $user, array $context): array
    {
        // Re-check immediately before issuing — a deactivated account
        // must never receive a token, even mid-challenge (D15).
        if (! $user->{UserSchema::IS_ACTIVE}) {
            throw new AccountNotActivatedException;
        }

        $deviceName = $context['device_name'] ?? 'unknown-device';

        // Replace only this device's previous token.
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
        ];
    }

    /**
     * External side effects: fire the UserSignedInEvent with the
     * cart-merge token. MUST be called after the caller's transaction
     * commits — events must never run inside a rollback-able scope.
     *
     * @param  array{device_name: string, remember: bool, cart_token: ?string}  $context
     */
    public function dispatchEvents(User $user, array $context): void
    {
        event(new UserSignedInEvent(
            $user->{UserSchema::ID},
            $user->{UserSchema::EMAIL},
            now()->toDateTimeString(),
            $context['cart_token'] ?? null,
        ));
    }
}
