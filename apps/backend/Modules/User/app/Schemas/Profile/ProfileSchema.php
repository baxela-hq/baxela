<?php

namespace Modules\User\Schemas\Profile;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\User\Schemas\Module;

class ProfileSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'users';

    public const string USER_ID = 'user_id';

    public const string FIRST_NAME = 'first_name';

    public const string LAST_NAME = 'last_name';
}
