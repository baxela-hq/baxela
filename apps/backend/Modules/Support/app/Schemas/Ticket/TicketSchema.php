<?php

namespace Modules\Support\Schemas\Ticket;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Support\Schemas\Module;

class TicketSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'tickets';

    public const string USER_ID = 'user_id';

    public const string ORDER_CODE = 'order_code';

    public const string SUBJECT = 'subject';

    public const string STATUS = 'status';

    public const string LAST_MESSAGE_AT = 'last_message_at';

    public const string RES_CUSTOMER_NAME = 'customer_name';

    public const string RES_CUSTOMER_EMAIL = 'customer_email';

    public const string RES_MESSAGES = 'messages';
}
