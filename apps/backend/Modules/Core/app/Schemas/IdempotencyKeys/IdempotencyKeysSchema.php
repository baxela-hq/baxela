<?php

namespace Modules\Core\Schemas\IdempotencyKeys;

use Modules\Core\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndCreatedAtTrait;

class IdempotencyKeysSchema
{
    use PkAndCreatedAtTrait;

    public const string TABLE = Module::DB_PREFIX.'idempotency_keys';

    public const string USER_ID = 'user_id';

    public const string PRINCIPAL_KEY = 'principal_key';

    public const string KEY = 'key';

    public const string STATUS = 'status';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public const string CLAIM_TOKEN = 'claim_token';

    public const string RESPONSE = 'response';

    public const string EXPIRES_AT = 'expires_at';

    public const string COMMITTED_AT = 'committed_at';
}
