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

    public const string COMMENT = 'comment';

    public const string REMEMBER_TOKEN = 'remember_token';

    /** Virtual payload field for assigning roles by id — not a column. */
    public const string ROLE_IDS = 'role_ids';

    /** Virtual sign-in field naming the device a token belongs to — not a column. */
    public const string DEVICE_NAME = 'device_name';

    /** Virtual sign-in field requesting the long ("remember me") token TTL — not a column. */
    public const string REMEMBER = 'remember';

    /** Serialization key for role objects attached to a user — not a column. */
    public const string ROLES = 'roles';
}
