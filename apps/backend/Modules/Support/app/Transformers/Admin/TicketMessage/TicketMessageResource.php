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
            // Runtime-only attributes attached by the show action; null when
            // the author no longer resolves.
            TicketMessageSchema::RES_AUTHOR_NAME => $this->resource->getAttribute(TicketMessageSchema::RES_AUTHOR_NAME),
            TicketMessageSchema::RES_AUTHOR_EMAIL => $this->resource->getAttribute(TicketMessageSchema::RES_AUTHOR_EMAIL),
            TicketMessageSchema::CREATED_AT => $this->{TicketMessageSchema::CREATED_AT},
        ];
    }
}
