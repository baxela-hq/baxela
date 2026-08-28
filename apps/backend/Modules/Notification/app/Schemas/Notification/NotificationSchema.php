<?php

namespace Modules\Notification\Schemas\Notification;

use Modules\Core\Schemas\Shared\PkAndCreatedAtTrait;
use Modules\Notification\Schemas\Module;

class NotificationSchema
{
    use PkAndCreatedAtTrait;

    public const string TABLE = Module::DB_PREFIX.'notifications';

    public const string USER_ID = 'user_id';

    public const string CODE = 'code';

    public const string AUDIENCE = 'audience';

    public const string TITLE = 'title';

    public const string BODY = 'body';

    public const string META = 'meta';

    public const string READ_AT = 'read_at';
}
