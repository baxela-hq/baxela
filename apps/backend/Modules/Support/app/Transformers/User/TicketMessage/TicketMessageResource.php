<?php

namespace Modules\Support\Transformers\User\TicketMessage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Schemas\TicketMessage\TicketMessageSchema;

class TicketMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array. Author ids stay private —
     * the sender side is all the storefront needs to render the thread.
     */
    public function toArray(Request $request): array
    {
        return [
            TicketMessageSchema::ID => $this->{TicketMessageSchema::ID},
            TicketMessageSchema::SENDER => $this->{TicketMessageSchema::SENDER},
            TicketMessageSchema::BODY => $this->{TicketMessageSchema::BODY},
            TicketMessageSchema::CREATED_AT => $this->{TicketMessageSchema::CREATED_AT},
        ];
    }
}
