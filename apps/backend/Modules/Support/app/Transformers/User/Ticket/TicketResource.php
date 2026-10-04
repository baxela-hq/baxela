<?php

namespace Modules\Support\Transformers\User\Ticket;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Transformers\User\TicketMessage\TicketMessageResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array. The ownership scope already
     * guarantees every record here belongs to the authenticated customer.
     */
    public function toArray(Request $request): array
    {
        return [
            TicketSchema::ID => $this->{TicketSchema::ID},
            TicketSchema::SUBJECT => $this->{TicketSchema::SUBJECT},
            TicketSchema::STATUS => $this->{TicketSchema::STATUS},
            TicketSchema::ORDER_CODE => $this->{TicketSchema::ORDER_CODE},
            TicketSchema::LAST_MESSAGE_AT => $this->{TicketSchema::LAST_MESSAGE_AT},
            TicketSchema::CREATED_AT => $this->{TicketSchema::CREATED_AT},
            TicketSchema::UPDATED_AT => $this->{TicketSchema::UPDATED_AT},
            TicketSchema::RES_MESSAGES => $this->whenLoaded(
                TicketSchema::RES_MESSAGES,
                fn () => TicketMessageResource::collection($this->{TicketSchema::RES_MESSAGES})
            ),
        ];
    }
}
