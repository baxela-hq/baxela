<?php

namespace Modules\Auth\Actions\Public\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Models\TwoFactorChallenge;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeSchema;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Support\TwoFactor\TwoFactorChallengeService;
use Modules\Auth\Support\TwoFactor\TwoFactorService;

/**
 * Completes the forced-enrollment flow (S.1 D9) for super-admins who
 * hold a valid ENROLLMENT challenge but no bearer token. secret()
 * generates the unconfirmed TOTP secret under the user lock (D14:
 * re-calling replaces it); confirm() runs the D6 sequence — verify
 * TOTP (updating the D8 replay guard), set confirmed_at + recovery
 * codes, consume the challenge, and issue the token INSIDE the
 * transaction, dispatching events only after commit.
 */
class CompleteTwoFactorEnrollmentAction
{
    public function __construct(
        protected TwoFactorChallengeService $challenges,
        protected TwoFactorService $twoFactor,
    ) {}

    /**
     * Step 1: generate the secret + QR for the challenge holder.
     *
     * @return object{secret: string, qr_code_uri: string}
     *
     * @throws AuthException
     */
    public function secret(string $challengeToken): object
    {
        $userId = $this->challenges->userIdFor($challengeToken);

        if ($userId === null) {
            throw AuthException::twoFactorChallengeInvalid();
        }

        return DB::transaction(function () use ($challengeToken, $userId): object {
            // D17: lock the user row first.
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            $challenge = $this->challenges->lockAndValidate(
                $userId,
                $challengeToken,
                TwoFactorChallengeTypeEnum::ENROLLMENT,
            );

            if ($challenge === null) {
                throw AuthException::twoFactorChallengeInvalid();
            }

            if ($user->{UserSchema::TWO_FACTOR_CONFIRMED_AT} !== null) {
                throw AuthException::twoFactorAlreadyEnrolled();
            }

            // D14: a re-called enable replaces the unconfirmed secret.
            $secret = $this->twoFactor->generateSecret();
            $user->{UserSchema::TWO_FACTOR_SECRET} = $secret;
            $user->save();

            return (object) [
                'secret' => $secret,
                'qr_code_uri' => $this->twoFactor->qrCodeUri($secret, $user->{UserSchema::EMAIL}),
            ];
        });
    }

    /**
     * Step 2: confirm with a TOTP code — enrollment + sign-in in one
     * atomic step. The token is issued inside the transaction (D6) so
     * a concurrent writer can never interleave with a half-enrolled
     * user.
     *
     * @return object{user: User, token: string, recovery_codes: list<string>}
     *
     * @throws AuthException
     */
    public function confirm(string $challengeToken, string $code): object
    {
        $userId = $this->challenges->userIdFor($challengeToken);

        if ($userId === null) {
            throw AuthException::twoFactorChallengeInvalid();
        }

        $rateKey = sprintf(VerifyTwoFactorAction::RATE_LIMIT_KEY, $userId);

        if (RateLimiter::tooManyAttempts($rateKey, VerifyTwoFactorAction::RATE_LIMIT_MAX_ATTEMPTS)) {
            throw AuthException::twoFactorTooManyAttempts();
        }

        $result = DB::transaction(function () use ($challengeToken, $code, $userId) {
            // D17: lock the user row first.
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            $challenge = $this->challenges->lockAndValidate(
                $userId,
                $challengeToken,
                TwoFactorChallengeTypeEnum::ENROLLMENT,
            );

            if ($challenge === null) {
                return ['status' => 'invalid_challenge'];
            }

            if ($user->{UserSchema::TWO_FACTOR_CONFIRMED_AT} !== null) {
                return ['status' => 'already_enrolled'];
            }

            if ($user->{UserSchema::TWO_FACTOR_SECRET} === null) {
                return ['status' => 'secret_missing'];
            }

            // Verify the TOTP code — this also updates the replay guard (D8).
            $matchedStep = $this->twoFactor->verifyCode($user, $code);

            if ($matchedStep === null) {
                return [
                    'status' => 'invalid_code',
                    'token_hash' => $challenge->{TwoFactorChallengeSchema::TOKEN_HASH},
                ];
            }

            $codes = $this->twoFactor->generateRecoveryCodes();
            $user->fill([
                UserSchema::TWO_FACTOR_RECOVERY_CODES => $this->twoFactor->hashRecoveryCodes($codes),
                UserSchema::TWO_FACTOR_CONFIRMED_AT => now(),
            ]);
            $user->save();

            $this->challenges->consume($challenge);

            // Issue the token INSIDE the transaction (D6) using the
            // challenge's original sign-in context.
            $context = $challenge->{TwoFactorChallengeSchema::CONTEXT} ?? [];
            $context = is_array($context) ? $context : [];
            $completion = app(CompleteAuthenticationAction::class);
            $tokenResult = $completion->issueToken($user, $context);

            return [
                'status' => 'success',
                'user' => $user,
                'token_result' => $tokenResult,
                'recovery_codes' => $codes,
                'context' => $context,
                'completion' => $completion,
            ];
        });

        if ($result['status'] === 'invalid_challenge') {
            throw AuthException::twoFactorChallengeInvalid();
        }

        if ($result['status'] === 'already_enrolled') {
            throw AuthException::twoFactorAlreadyEnrolled();
        }

        if ($result['status'] === 'secret_missing') {
            throw AuthException::twoFactorNotEnrolled();
        }

        if ($result['status'] === 'invalid_code') {
            // Increment OUTSIDE the transaction — the rollback would
            // undo it (same pattern as VerifyTwoFactorAction).
            TwoFactorChallenge::query()
                ->where(TwoFactorChallengeSchema::TOKEN_HASH, $result['token_hash'])
                ->increment(TwoFactorChallengeSchema::FAILED_ATTEMPTS);

            RateLimiter::hit($rateKey, VerifyTwoFactorAction::RATE_LIMIT_DECAY_SECONDS);

            throw AuthException::twoFactorInvalidCode();
        }

        /** @var User $user */
        $user = $result['user'];
        /** @var CompleteAuthenticationAction $completion */
        $completion = $result['completion'];
        $completion->dispatchEvents($user, is_array($result['context']) ? $result['context'] : []);

        RateLimiter::clear($rateKey);

        return (object) [
            'user' => $user,
            'token' => $result['token_result']['token'],
            'recovery_codes' => $result['recovery_codes'],
        ];
    }
}
