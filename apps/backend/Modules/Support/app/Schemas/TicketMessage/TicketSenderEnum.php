<?php

namespace Modules\Support\Schemas\TicketMessage;

enum TicketSenderEnum: string
{
    case CUSTOMER = 'customer';

    case ADMIN = 'admin';
}
