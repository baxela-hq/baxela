<?php

namespace Modules\Tenant\Schemas\Tenant;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Core\Schemas\Shared\SoftDeleteTrait;
use Modules\Tenant\Schemas\Module;

class TenantSchema
{
    use PkAndTimestampsTrait, SoftDeleteTrait;

    public const string TABLE = Module::DB_PREFIX.'tenants';

    public const string NAME = 'name';

    public const string SLUG = 'slug';

    public const string EMAIL = 'email';

    public const string STATUS = 'status';

    /** Active: served normally. */
    public const string STATUS_ACTIVE = 'active';

    /** Suspended: storefront closed, members denied, jobs skipped. */
    public const string STATUS_SUSPENDED = 'suspended';

    public const string RES_DOMAINS = 'domains';
}
