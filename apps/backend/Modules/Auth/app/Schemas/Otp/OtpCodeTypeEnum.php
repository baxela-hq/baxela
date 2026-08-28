<?php

namespace Modules\Auth\Schemas\Otp;

enum OtpCodeTypeEnum: string
{
    case EMAIL = 'email';
    case MOBILE = 'mobile';
}
