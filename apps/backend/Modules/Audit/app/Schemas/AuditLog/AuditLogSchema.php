<?php

namespace Modules\Audit\Schemas\AuditLog;

use Modules\Audit\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class AuditLogSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'logs';

    public const string USER_ID = 'user_id';

    public const string MODULE = 'module';

    public const string ENTITY = 'entity';

    public const string ACTION = 'action';

    public const string ENTITY_ID = 'entity_id';

    public const string PROPERTIES = 'properties';

    public const string IP_ADDRESS = 'ip_address';

    public const string USER_AGENT = 'user_agent';

    /** Filter names for the date-range custom filter — not columns. */
    public const string FILTER_CREATED_FROM = 'created_from';

    public const string FILTER_CREATED_TO = 'created_to';

    /** Serialization key for the acting user object — not a column. */
    public const string RES_USER = 'user';
}
