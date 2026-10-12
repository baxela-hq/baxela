<?php

namespace Modules\Notification\Schemas\TelegramSubscription;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Notification\Schemas\Module;

class TelegramSubscriptionSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'telegram_subscriptions';

    public const string USER_ID = 'user_id';

    /** Telegram chat the bot delivers to; unique — one user per chat. */
    public const string CHAT_ID = 'chat_id';

    /** @username of the linked Telegram account, as last seen by the bot. */
    public const string USERNAME = 'username';

    public const string LINKED_AT = 'linked_at';

    // Request/response keys
    public const string CODE = 'code';

    public const string BOT_USERNAME = 'bot_username';

    public const string BOT_URL = 'bot_url';
}
