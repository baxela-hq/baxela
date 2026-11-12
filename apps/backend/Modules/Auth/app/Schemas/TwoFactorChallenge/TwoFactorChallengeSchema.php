<?php

namespace Modules\Auth\Schemas\TwoFactorChallenge;

use Modules\Auth\Schemas\Module;

class TwoFactorChallengeSchema
{
    public const string TABLE = Module::DB_PREFIX.'two_factor_challenges';

    /** SHA-256 hash of the raw 64-char hex token — never the raw token. */
    public const string TOKEN_HASH = 'token_hash';

    public const string USER_ID = 'user_id';

    /** sign_in | enrollment (TwoFactorChallengeTypeEnum). */
    public const string TYPE = 'type';

    /** Encrypted JSON: {device_name, remember, cart_token} — explicit allowlist. */
    public const string CONTEXT = 'context';

    public const string FAILED_ATTEMPTS = 'failed_attempts';

    public const string EXPIRES_AT = 'expires_at';

    public const string CONSUMED_AT = 'consumed_at';
}
