<?php

namespace Modules\Support\Actions\Admin\Ticket;

use Modules\Support\Models\Ticket;

abstract class AbstractTicketAction
{
    public function __construct(protected Ticket $model) {}
}
