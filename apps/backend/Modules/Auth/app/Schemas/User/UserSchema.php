<?php

namespace Modules\Auth\Schemas\User;

use Modules\Auth\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class UserSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'users';

    public const string EMAIL = 'email';

    public const string EMAIL_VERIFIED_AT = 'email_verified_at';

    public const string MOBILE = 'mobile';

    public const string MOBILE_VERIFIED_AT = 'mobile_verified_at';

    public const string PASSWORD = 'password';

    public const string IS_ACTIVE = 'is_active';

    public const string IS_ADMIN = 'is_admin';

    public const string COMMENT = 'comment';

    public const string REMEMBER_TOKEN = 'remember_token';
}
