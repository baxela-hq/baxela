<?php

namespace Modules\Support\Transformers\Admin\TicketMessage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;

class TicketMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            TicketMessageSchema::ID => $this->{TicketMessageSchema::ID},
            TicketMessageSchema::TICKET_ID => $this->{TicketMessageSchema::TICKET_ID},
            TicketMessageSchema::USER_ID => $this->{TicketMessageSchema::USER_ID},
            TicketMessageSchema::SENDER => $this->{TicketMessageSchema::SENDER},
            TicketMessageSchema::BODY => $this->{TicketMessageSchema::BODY},
            TicketMessageSchema::CREATED_AT => $this->{TicketMessageSchema::CREATED_AT},
        ];
    }
}
