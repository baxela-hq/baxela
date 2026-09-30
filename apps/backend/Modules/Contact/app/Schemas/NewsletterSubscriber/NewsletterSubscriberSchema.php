<?php

namespace Modules\Contact\Schemas\NewsletterSubscriber;

use Modules\Contact\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class NewsletterSubscriberSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'newsletter_subscribers';

    public const string EMAIL = 'email';

    public const string LOCALE = 'locale';

    public const string STATUS = 'status';
}
