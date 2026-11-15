<?php

namespace Modules\Contact\Transformers\Public\ContactMessage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;

/**
 * @mixin ContactMessage
 */
class ContactMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array. Only non-sensitive fields are
     * exposed to unauthenticated submitters.
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ContactMessageSchema::ID => $this->{ContactMessageSchema::ID},
            ContactMessageSchema::STATUS => $this->{ContactMessageSchema::STATUS},
            ContactMessageSchema::CREATED_AT => $this->{ContactMessageSchema::CREATED_AT},
        ];
    }
}
