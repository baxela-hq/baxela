<?php

namespace Modules\Auth\Schemas\Role;

use Modules\Auth\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class RoleSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'roles';

    public const string NAME = 'name';

    public const string GUARD_NAME = 'guard_name';

    /** Virtual payload field for syncing permissions by id — not a column. */
    public const string PERMISSION_IDS = 'permission_ids';

    /** Serialization key for permission objects attached to a role — not a column. */
    public const string PERMISSIONS = 'permissions';
}
