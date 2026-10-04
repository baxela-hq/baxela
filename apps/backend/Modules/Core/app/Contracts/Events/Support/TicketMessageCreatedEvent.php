<?php

namespace Modules\Core\Contracts\Events\Support;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class TicketMessageCreatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $ticket_id;

    /**
     * The ticket owner (the customer), regardless of who wrote the message.
     */
    public int $user_id;

    /**
     * The author of the message (customer or staff member).
     */
    public int $sender_user_id;

    public string $sender;

    public string $subject;

    public ?string $order_code;

    public string $body;

    public ?string $locale = null;
}
