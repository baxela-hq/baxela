<?php

namespace Modules\Auth\Exceptions;

use Modules\Auth\Schemas\Module;
use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case ROLE_CREATION_FAILED = Module::NAME_LOWER.'.role.creation_failed';

    case ROLE_UPDATE_FAILED = Module::NAME_LOWER.'.role.update_failed';

    case ROLE_DELETION_FAILED = Module::NAME_LOWER.'.role.deletion_failed';

    case ROLE_IN_USE = Module::NAME_LOWER.'.role.in_use';

    case OTP_TOO_MANY_REQUESTS = 'auth.otp.too_many_requests';
    case OTP_INVALID = 'auth.otp.invalid';
    case AUTH_INVALID_CREDENTIALS = 'auth.credentials.invalid';
    case AUTH_ACCOUNT_ALREADY_ACTIVATED = 'auth.account.already_active';
    case AUTH_ACCOUNT_NOT_ACTIVATED = 'auth.account.not_active';

    // S.4 account lockout
    case AUTH_SIGN_IN_LOCKED = 'auth.sign_in.locked';

    case AUTH_SIGN_IN_BUSY = 'auth.sign_in.busy';

    // Two-factor authentication (S.1)
    case TWO_FACTOR_INVALID_CODE = 'auth.two_factor.invalid_code';

    case TWO_FACTOR_CHALLENGE_INVALID = 'auth.two_factor.challenge_invalid';

    case TWO_FACTOR_TOO_MANY_ATTEMPTS = 'auth.two_factor.too_many_attempts';

    case TWO_FACTOR_NOT_ENROLLED = 'auth.two_factor.not_enrolled';

    case TWO_FACTOR_ALREADY_ENROLLED = 'auth.two_factor.already_enrolled';

}
