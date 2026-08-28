<?php

namespace Modules\Core\Schemas\IdempotencyKeys;

use Modules\Core\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndCreatedAtTrait;

class IdempotencyKeysSchema
{
    use PkAndCreatedAtTrait;

    public const string TABLE = Module::DB_PREFIX.'idempotency_keys';

    public const string USER_ID = 'user_id';

    public const string KEY = 'key';

    public const string RESPONSE = 'response';

    public const string EXPIRED_AT = 'expired_at';
}
