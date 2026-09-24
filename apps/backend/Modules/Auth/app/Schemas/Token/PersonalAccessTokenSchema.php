<?php

namespace Modules\Auth\Schemas\Token;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class PersonalAccessTokenSchema
{
    use PkAndTimestampsTrait;

    /** Sanctum's stock table — not prefixed, unlike module tables. */
    public const string TABLE = 'personal_access_tokens';

    public const string TOKENABLE_TYPE = 'tokenable_type';

    public const string TOKENABLE_ID = 'tokenable_id';

    /** Device name the token was issued for. */
    public const string NAME = 'name';

    public const string TOKEN = 'token';

    public const string ABILITIES = 'abilities';

    public const string LAST_USED_AT = 'last_used_at';

    public const string EXPIRES_AT = 'expires_at';

    /** Serialization key marking the caller's own session — not a column. */
    public const string IS_CURRENT = 'is_current';
}
