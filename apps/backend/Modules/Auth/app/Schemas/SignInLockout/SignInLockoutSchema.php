<?php

namespace Modules\Auth\Schemas\SignInLockout;

use Modules\Auth\Schemas\Module;

class SignInLockoutSchema
{
    public const string TABLE = Module::DB_PREFIX.'sign_in_lockouts';

    /** SHA-256 of the canonical email — raw addresses never hit rows. */
    public const string EMAIL_HASH = 'email_hash';

    public const string FAILED_ATTEMPTS = 'failed_attempts';

    public const string LAST_FAILED_AT = 'last_failed_at';

    public const string LOCKED_UNTIL = 'locked_until';

    public const string LOCKED_AT = 'locked_at';
}
