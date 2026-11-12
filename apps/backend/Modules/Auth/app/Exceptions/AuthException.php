<?php

namespace Modules\Auth\Exceptions;

use Modules\Core\Exceptions\BaseException;

class AuthException extends BaseException
{
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
