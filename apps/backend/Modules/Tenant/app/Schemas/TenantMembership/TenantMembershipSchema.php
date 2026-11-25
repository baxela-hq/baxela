<?php

namespace Modules\Tenant\Schemas\TenantMembership;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Tenant\Schemas\Module;

class TenantMembershipSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'memberships';

    public const string TENANT_ID = 'tenant_id';

    /** Plain id column — no cross-module FK (module-boundary rule); the user is validated through the Auth gateway. */
    public const string USER_ID = 'user_id';

    public const string STATUS = 'status';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_INVITED = 'invited';

    public const string STATUS_SUSPENDED = 'suspended';

    public const string IS_OWNER = 'is_owner';

    public const string JOINED_AT = 'joined_at';
}
