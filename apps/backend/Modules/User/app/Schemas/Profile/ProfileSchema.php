<?php

namespace Modules\User\Schemas\Profile;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\User\Schemas\Module;

class ProfileSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'users';

    public const string USER_ID = 'user_id';

    public const string FULL_NAME = 'full_name';

    public const string DISPLAY_NAME = 'display_name';

    public const string BIO = 'bio';

    public const string AVATAR = 'avatar';

    public const string GENDER = 'gender';

    public const string DATE_OF_BIRTH = 'date_of_birth';
}
