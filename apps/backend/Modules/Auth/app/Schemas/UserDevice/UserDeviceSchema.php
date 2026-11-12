<?php

namespace Modules\Auth\Schemas\UserDevice;

use Modules\Auth\Schemas\Module;

class UserDeviceSchema
{
    public const string TABLE = Module::DB_PREFIX.'user_devices';

    public const string USER_ID = 'user_id';

    /** Client-reported label (sign-in `device_name`), capped at 100 chars. */
    public const string DEVICE_NAME = 'device_name';

    public const string FIRST_SEEN_AT = 'first_seen_at';

    public const string LAST_SEEN_AT = 'last_seen_at';
}
