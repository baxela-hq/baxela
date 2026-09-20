<?php

namespace Modules\Notification\Schemas\PushSubscription;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Notification\Schemas\Module;

class PushSubscriptionSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'push_subscriptions';

    public const string USER_ID = 'user_id';

    /** Push service endpoint URL; unique — one row per browser registration. */
    public const string ENDPOINT = 'endpoint';

    /** Client encryption key (base64url), from the browser Subscription keys. */
    public const string P256DH = 'p256dh';

    /** Client auth secret (base64url), from the browser Subscription keys. */
    public const string AUTH = 'auth';

    public const string USER_AGENT = 'user_agent';

    /** UI locale captured at subscribe time, e.g. for payload direction. */
    public const string LOCALE = 'locale';

    public const string KEYS = 'keys';

    public const string PUBLIC_KEY = 'public_key';
}
