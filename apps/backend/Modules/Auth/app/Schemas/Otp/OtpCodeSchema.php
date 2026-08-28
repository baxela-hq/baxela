<?php

namespace Modules\Auth\Schemas\Otp;

use Modules\Auth\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class OtpCodeSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'otp_codes';

    public const string MOBILE = 'mobile';

    public const string EMAIL = 'email';

    public const string TYPE = 'type';

    public const string ACTION = 'action';

    public const string CODE = 'code';

    public const string EXPIRES_AT = 'expires_at';

    public const string IS_USED = 'is_used';
}
