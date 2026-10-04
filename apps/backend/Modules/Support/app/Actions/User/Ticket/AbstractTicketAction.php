<?php

namespace Modules\Support\Actions\User\Ticket;

use Modules\Support\Models\User\Ticket;

abstract class AbstractTicketAction
{
    public function __construct(protected Ticket $model) {}
}
