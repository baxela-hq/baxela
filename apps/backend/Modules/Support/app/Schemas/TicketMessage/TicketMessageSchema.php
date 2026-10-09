<?php

namespace Modules\Support\Schemas\TicketMessage;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Support\Schemas\Module;

class TicketMessageSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'ticket_messages';

    public const string TICKET_ID = 'ticket_id';

    public const string USER_ID = 'user_id';

    public const string SENDER = 'sender';

    public const string BODY = 'body';

    /** Response-only: author display name / email, resolved through gateways. */
    public const string RES_AUTHOR_NAME = 'author_name';

    public const string RES_AUTHOR_EMAIL = 'author_email';
}
