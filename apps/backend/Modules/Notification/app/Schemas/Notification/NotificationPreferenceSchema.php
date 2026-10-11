<?php

namespace Modules\Notification\Schemas\Notification;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Notification\Schemas\Module;

class NotificationPreferenceSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'preferences';

    public const string USER_ID = 'user_id';

    public const string CODE = 'code';

    public const string AUDIENCE = 'audience';

    public const string CHANNEL = 'channel';

    public const string IS_ENABLED = 'is_enabled';

    // Response/request keys
    public const string RES_LOCKED = 'locked';

    public const string RES_CHANNELS = 'channels';

    public const string RES_ENABLED = 'enabled';

    public const string RES_IS_DEFAULT = 'is_default';
}
