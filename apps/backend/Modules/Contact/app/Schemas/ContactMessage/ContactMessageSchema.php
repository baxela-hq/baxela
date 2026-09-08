<?php

namespace Modules\Contact\Schemas\ContactMessage;

use Modules\Contact\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class ContactMessageSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'messages';

    public const string NAME = 'name';

    public const string EMAIL = 'email';

    public const string PHONE = 'phone';

    public const string SUBJECT = 'subject';

    public const string CONTENT = 'content';

    public const string STATUS = 'status';

    public const string IP_ADDRESS = 'ip_address';
}
