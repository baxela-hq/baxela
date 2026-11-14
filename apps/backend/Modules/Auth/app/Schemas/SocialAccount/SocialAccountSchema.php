<?php

namespace Modules\Auth\Schemas\SocialAccount;

use Modules\Auth\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class SocialAccountSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'social_accounts';

    public const string USER_ID = 'user_id';

    /** Provider identifier ('google' for now) — assigned internally, never request input. */
    public const string PROVIDER = 'provider';

    /** The provider's immutable account id (Google: the OIDC `sub` claim). */
    public const string PROVIDER_USER_ID = 'provider_user_id';
}
