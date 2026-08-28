<?php

namespace Modules\Media\Schemas\Folder;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Media\Schemas\Module;

class FolderSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'folders';

    public const string USER_ID = 'user_id';

    public const string PARENT_ID = 'parent_id';

    public const string NAME = 'name';

    public const string POSITION = 'position';
}
