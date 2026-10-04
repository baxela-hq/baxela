<?php

namespace Modules\Support\Transformers\Admin\Ticket;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Schemas\Ticket\TicketSchema;
use Modules\Support\Transformers\Admin\TicketMessage\TicketMessageResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array. Customer name/email are
     * stashed on the record by the action via the user gateways.
     */
    public function toArray(Request $request): array
    {
        return [
            TicketSchema::ID => $this->{TicketSchema::ID},
            TicketSchema::USER_ID => $this->{TicketSchema::USER_ID},
            TicketSchema::RES_CUSTOMER_NAME => $this->{TicketSchema::RES_CUSTOMER_NAME},
            TicketSchema::RES_CUSTOMER_EMAIL => $this->{TicketSchema::RES_CUSTOMER_EMAIL},
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
