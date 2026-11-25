<?php

namespace Modules\Tenant\Schemas\TenantDomain;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Tenant\Schemas\Module;

class TenantDomainSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'domains';

    public const string TENANT_ID = 'tenant_id';

    public const string DOMAIN = 'domain';

    public const string STATUS = 'status';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_VERIFIED = 'verified';

    public const string STATUS_RELEASED = 'released';

    public const string IS_PRIMARY = 'is_primary';

    public const string VERIFIED_AT = 'verified_at';

    public const string VERIFICATION_TOKEN_HASH = 'verification_token_hash';

    public const string VERIFICATION_METHOD = 'verification_method';

    public const string METHOD_HTTPS = 'https';

    public const string METHOD_DNS = 'dns';

    public const string VERIFICATION_EXPIRES_AT = 'verification_expires_at';

    public const string RELEASED_AT = 'released_at';

    public const string COOLING_OFF_UNTIL = 'cooling_off_until';
}
