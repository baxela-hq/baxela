<?php

namespace Modules\Core\Contracts\Events\Support;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class TicketDeletedEvent extends AbstractBaseEvent
{
    public int $id;
}
