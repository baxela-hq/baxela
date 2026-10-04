<?php

namespace Modules\Core\Contracts\Events\Support;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class TicketCreatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $subject;

    public ?string $order_code;

    public string $status;

    public string $body;

    public ?string $locale = null;
}
