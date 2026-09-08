<?php

namespace Modules\Contact\Transformers\Admin\ContactMessage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;

class ContactMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            ContactMessageSchema::ID => $this->{ContactMessageSchema::ID},
            ContactMessageSchema::NAME => $this->{ContactMessageSchema::NAME},
            ContactMessageSchema::EMAIL => $this->{ContactMessageSchema::EMAIL},
            ContactMessageSchema::PHONE => $this->{ContactMessageSchema::PHONE},
            ContactMessageSchema::SUBJECT => $this->{ContactMessageSchema::SUBJECT},
            ContactMessageSchema::CONTENT => $this->{ContactMessageSchema::CONTENT},
            ContactMessageSchema::STATUS => $this->{ContactMessageSchema::STATUS},
            ContactMessageSchema::IP_ADDRESS => $this->{ContactMessageSchema::IP_ADDRESS},
            ContactMessageSchema::CREATED_AT => $this->{ContactMessageSchema::CREATED_AT},
            ContactMessageSchema::UPDATED_AT => $this->{ContactMessageSchema::UPDATED_AT},
        ];
    }
}
