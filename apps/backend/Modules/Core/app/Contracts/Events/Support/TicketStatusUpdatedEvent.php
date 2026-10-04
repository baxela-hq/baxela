<?php

namespace Modules\Core\Contracts\Events\Support;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class TicketStatusUpdatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $status;

    public ?string $locale = null;
}
