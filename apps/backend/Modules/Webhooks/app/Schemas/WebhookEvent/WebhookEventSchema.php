<?php

namespace Modules\Webhooks\Schemas\WebhookEvent;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Webhooks\Schemas\Module;

class WebhookEventSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'events';

    /** uuid PK */
    public const string ID = 'id';

    /** Deterministic business identity, e.g. "order.paid:1024:payment-9". */
    public const string SOURCE_KEY = 'source_key';

    public const string EVENT_TYPE = 'event_type';

    public const string PAYLOAD = 'payload';

    public const string PROCESSED_AT = 'processed_at';
}
