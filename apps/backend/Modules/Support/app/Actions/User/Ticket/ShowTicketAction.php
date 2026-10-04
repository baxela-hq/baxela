<?php

namespace Modules\Support\Actions\User\Ticket;

use Modules\Support\Schemas\Ticket\TicketSchema;

class ShowTicketAction extends AbstractTicketAction
{
    public function handle(string $id)
    {
        // the ownership scope turns someone else's ticket into a 404
        return $this->model->query()
            ->with(TicketSchema::RES_MESSAGES)
            ->findOrFail($id);
    }
}
