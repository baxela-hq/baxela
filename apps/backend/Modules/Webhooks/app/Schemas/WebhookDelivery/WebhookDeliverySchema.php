<?php

namespace Modules\Webhooks\Schemas\WebhookDelivery;

use Modules\Core\Schemas\Shared\PkAndCreatedAtTrait;
use Modules\Webhooks\Schemas\Module;

class WebhookDeliverySchema
{
    use PkAndCreatedAtTrait;

    public const string TABLE = Module::DB_PREFIX.'deliveries';

    /** uuid PK */
    public const string ID = 'id';

    public const string EVENT_ID = 'event_id';

    public const string ENDPOINT_ID = 'endpoint_id';

    public const string EVENT_TYPE = 'event_type';

    public const string STATUS = 'status';

    /** Ownership token: state updates are conditional on it (gap-C2 D4). */
    public const string CLAIM_TOKEN = 'claim_token';

    public const string CLAIMED_AT = 'claimed_at';

    /** Real HTTP attempts (gap-C2 D9) — created before send, finalized after. */
    public const string ATTEMPTS = 'attempts';

    public const string ATTEMPT_COUNT = 'attempt_count';

    public const string NEXT_ATTEMPT_AT = 'next_attempt_at';

    public const string RESPONSE_STATUS = 'response_status';

    public const string RESPONSE_SNIPPET = 'response_snippet';

    public const string ERROR_CATEGORY = 'error_category';

    public const string DURATION_MS = 'duration_ms';

    public const string DELIVERED_AT = 'delivered_at';
}
