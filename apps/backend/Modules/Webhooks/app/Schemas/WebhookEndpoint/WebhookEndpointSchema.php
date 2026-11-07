<?php

namespace Modules\Webhooks\Schemas\WebhookEndpoint;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Webhooks\Schemas\Module;

class WebhookEndpointSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'endpoints';

    public const string NAME = 'name';

    public const string URL = 'url';

    /** HMAC secret, encrypted at rest (gap-C2 D8). */
    public const string SECRET = 'secret';

    /** Subscribed event types (JSON list, validated against the catalog). */
    public const string EVENTS = 'events';

    public const string IS_ACTIVE = 'is_active';

    /** Archive-not-delete (gap-C2 D12): history must survive. */
    public const string IS_ARCHIVED = 'is_archived';

    public const string CONSECUTIVE_FAILURES = 'consecutive_failures';

    public const string CREATED_BY = 'created_by';
}
