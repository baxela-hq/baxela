<?php

namespace Modules\Auth\Exceptions;

use Modules\Core\Exceptions\ErrorCodeInterface;

enum ErrorCodeEnum: string implements ErrorCodeInterface
{
    case OTP_TOO_MANY_REQUESTS = 'auth.otp.too_many_requests';
    case OTP_INVALID = 'auth.otp.invalid';
    case AUTH_INVALID_CREDENTIALS = 'auth.credentials.invalid';
    case AUTH_ACCOUNT_ALREADY_ACTIVATED = 'auth.account.already_active';
    case AUTH_ACCOUNT_NOT_ACTIVATED = 'auth.account.not_active';

}
