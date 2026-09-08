<?php

namespace Modules\Core\Contracts\Events\Contact;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class ContactMessageDeletedEvent extends AbstractBaseEvent
{
    public int $id;
}
