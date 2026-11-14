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

    // Google sign-in (customers only)
    public static function googleInvalidToken(): self
    {
        return new self(ErrorCodeEnum::GOOGLE_INVALID_TOKEN->value, 401);
    }

    public static function googleNonceInvalid(): self
    {
        return new self(ErrorCodeEnum::GOOGLE_NONCE_INVALID->value, 401);
    }

    public static function googleDisabled(): self
    {
        return new self(ErrorCodeEnum::GOOGLE_DISABLED->value, 403);
    }

    public static function googleEmailUnverified(): self
    {
        return new self(ErrorCodeEnum::GOOGLE_EMAIL_UNVERIFIED->value, 403);
    }

    public static function googleEmailExists(): self
    {
        return new self(ErrorCodeEnum::GOOGLE_EMAIL_EXISTS->value, 409);
    }

    public static function googleAdminForbidden(): self
    {
        return new self(ErrorCodeEnum::GOOGLE_ADMIN_FORBIDDEN->value, 403);
    }

    /** Fail-closed: Redis/JWKS dependency unavailable — reject, never degrade. */
    public static function googleUnavailable(): self
    {
        return new self(ErrorCodeEnum::GOOGLE_UNAVAILABLE->value, 503);
    }
}
