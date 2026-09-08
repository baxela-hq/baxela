<?php

namespace Modules\Core\Contracts\Events\Contact;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class ContactMessageStatusUpdatedEvent extends AbstractBaseEvent
{
    public int $id;

    public string $status;
}
