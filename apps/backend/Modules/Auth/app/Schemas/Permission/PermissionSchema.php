<?php

namespace Modules\Auth\Schemas\Permission;

use Modules\Auth\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class PermissionSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'permissions';

    public const string NAME = 'name';

    public const string GUARD_NAME = 'guard_name';
}
