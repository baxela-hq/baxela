<?php

namespace Modules\Auth\Schemas\Otp;

enum OtpCodeActionEnum: string
{
    case VERIFY_EMAIL = 'verify_email';
    case FORGOT_PASSWORD = 'forgot_password';
}
