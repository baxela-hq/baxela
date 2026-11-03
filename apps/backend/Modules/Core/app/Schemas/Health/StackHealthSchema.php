<?php

namespace Modules\Core\Schemas\Health;

class StackHealthSchema
{
    /**
     * Computed wire fields for the public stack-health payload (no table).
     */
    public const string STATUS = 'status';

    public const string CHECKED_AT = 'checked_at';

    public const string CHECKS = 'checks';

    public const string ERROR = 'error';

    public const string DRIVER = 'driver';

    public const string PENDING = 'pending';

    public const string FAILED = 'failed';

    public const string STATUS_OK = 'ok';

    public const string STATUS_DEGRADED = 'degraded';

    public const string STATUS_DOWN = 'down';
}
