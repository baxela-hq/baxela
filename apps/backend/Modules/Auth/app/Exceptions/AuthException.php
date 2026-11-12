<?php

namespace Modules\Auth\Exceptions;

use Modules\Core\Exceptions\BaseException;

class AuthException extends BaseException
{
    // Sign-in lockout (S.4)
    public static function signInAccountLocked(int $seconds, int $minutes): self
    {
        return new self(
            ErrorCodeEnum::AUTH_SIGN_IN_LOCKED->value,
            429,
            meta: ['retry_after_seconds' => $seconds, 'retry_after_minutes' => $minutes],
            messageParams: ['minutes' => $minutes],
        );
    }

    public static function signInBusy(): self
    {
        return new self(ErrorCodeEnum::AUTH_SIGN_IN_BUSY->value, 503);
    }

    // Two-factor authentication (S.1)
    public static function twoFactorInvalidCode(): self
    {
        return new self(ErrorCodeEnum::TWO_FACTOR_INVALID_CODE->value);
    }

    public static function twoFactorChallengeInvalid(): self
    {
        return new self(ErrorCodeEnum::TWO_FACTOR_CHALLENGE_INVALID->value);
    }

    public static function twoFactorTooManyAttempts(): self
    {
        return new self(ErrorCodeEnum::TWO_FACTOR_TOO_MANY_ATTEMPTS->value, 429);
    }

    public static function twoFactorNotEnrolled(): self
    {
        return new self(ErrorCodeEnum::TWO_FACTOR_NOT_ENROLLED->value);
    }

    public static function twoFactorAlreadyEnrolled(): self
    {
        return new self(ErrorCodeEnum::TWO_FACTOR_ALREADY_ENROLLED->value);
    }
}
