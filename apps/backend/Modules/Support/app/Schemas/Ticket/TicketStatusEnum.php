<?php

namespace Modules\Support\Schemas\Ticket;

enum TicketStatusEnum: string
{
    case OPEN = 'open';

    case ANSWERED = 'answered';

    case CLOSED = 'closed';
}
