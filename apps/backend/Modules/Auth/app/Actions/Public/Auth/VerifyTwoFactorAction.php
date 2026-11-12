<?php

namespace Modules\Auth\Actions\Public\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Models\TwoFactorChallenge;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeSchema;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeTypeEnum;
use Modules\Auth\Support\TwoFactor\TwoFactorChallengeService;
use Modules\Auth\Support\TwoFactor\TwoFactorService;

/**
 * The D6 verification sequence: lock user (D17) → lock challenge →
 * verify code (TOTP with replay guard D8, or recovery code D5) →
 * consume challenge → ISSUE TOKEN inside the transaction → commit →
 * dispatch events. On failure: increment the counter OUTSIDE the
 * main transaction (the exception would roll it back), then throw.
 */
class VerifyTwoFactorAction
{
    public const string RATE_LIMIT_KEY = '2fa:%d';

    public const int RATE_LIMIT_MAX_ATTEMPTS = 10;

    public const int RATE_LIMIT_DECAY_SECONDS = 900;

    public function __construct(
        protected TwoFactorChallengeService $challenges,
        protected TwoFactorService $twoFactor,
    ) {}

    /**
     * @throws AuthException
     */
    public function handle(string $challengeToken, string $code): object
    {
        $userId = $this->challenges->userIdFor($challengeToken);

        if ($userId === null) {
            throw AuthException::twoFactorChallengeInvalid();
        }

        $rateKey = sprintf(self::RATE_LIMIT_KEY, $userId);

        if (RateLimiter::tooManyAttempts($rateKey, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            throw AuthException::twoFactorTooManyAttempts();
        }

        $result = DB::transaction(function () use ($challengeToken, $code, $userId) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            $challenge = $this->challenges->lockAndValidate(
                $userId,
                $challengeToken,
                TwoFactorChallengeTypeEnum::SIGN_IN,
            );

            if ($challenge === null) {
                return ['status' => 'invalid_challenge'];
            }

            $verified = $this->twoFactor->verifyCode($user, $code);

            if ($verified === null) {
                $verified = $this->twoFactor->verifyAndConsumeRecoveryCode($user, $code)
                    ? 'recovery'
                    : null;
            }

            if ($verified === null) {
                return [
                    'status' => 'invalid_code',
                    'token_hash' => $challenge->{TwoFactorChallengeSchema::TOKEN_HASH},
                ];
            }

            $this->challenges->consume($challenge);
            $user->save();

            $context = $challenge->{TwoFactorChallengeSchema::CONTEXT} ?? [];
            $completion = app(CompleteAuthenticationAction::class);
            $tokenResult = $completion->issueToken($user, is_array($context) ? $context : []);

            return [
                'status' => 'success',
                'user' => $user,
                'token_result' => $tokenResult,
                'context' => $context,
                'completion' => $completion,
            ];
        });

        if ($result['status'] === 'invalid_challenge') {
            throw AuthException::twoFactorChallengeInvalid();
        }

        if ($result['status'] === 'invalid_code') {
            TwoFactorChallenge::query()
                ->where(TwoFactorChallengeSchema::TOKEN_HASH, $result['token_hash'])
                ->increment(TwoFactorChallengeSchema::FAILED_ATTEMPTS);

            RateLimiter::hit($rateKey, self::RATE_LIMIT_DECAY_SECONDS);

            throw AuthException::twoFactorInvalidCode();
        }

        /** @var User $user */
        $user = $result['user'];
        /** @var CompleteAuthenticationAction $completion */
        $completion = $result['completion'];
        $completion->dispatchEvents($user, is_array($result['context']) ? $result['context'] : []);

        RateLimiter::clear($rateKey);

        return (object) $result['token_result'];
    }
}
